// ============================================================
// SMART PLUG 3-Slot — Firmware Final (Production)
// ESP32 + Firebase RTDB + PZEM-004T + DHT22 + LCD I2C + 3 Relay
// ============================================================
//
// PIN GPIO MAP:
//  ┌─────────────────────────────────────────────────────────┐
//  │ Tombol (INPUT_PULLUP)   : BTN1=27, BTN2=14, BTN3=12   │
//  │ LED Ring (OUTPUT)       : LED1=32, LED2=33, LED3=25    │
//  │ Relay 4-Ch (Active-LOW) : IN1=23, IN2=19, IN3=18      │
//  │                           IN4=26 (spare, selalu OFF)   │
//  │ LCD I2C 16x2            : SDA=21, SCL=22, Addr=0x27   │
//  │ DHT22                   : DATA=GPIO4                   │
//  │ PZEM-004T (UART2)       : RX2=GPIO16, TX2=GPIO17      │
//  └─────────────────────────────────────────────────────────┘
//
// Alur Kerja Per Slot:
//  STANDBY → [Tombol Ditekan] → WAITING_PAYMENT
//          → [QRIS Dibayar via Webhook Mayar] → ACTIVE (relay ON, countdown)
//          → [Countdown Habis / Force Stop Dashboard] → STANDBY
//
// Firebase RTDB Structure (Dual-Node Kompatibilitas):
//  /slots/slotX   { status, duration_seconds, amount_paid, activated_at, expires_at }
//  /slotX         { status, active_duration, started_at, nominal_paid }
//  /system/active_selection  { slot, status, timestamp }
//  /sensors       { voltage, current, power, energy, temperature, humidity, updated_at }
//  /config/pricing { base_price, base_duration_seconds }
//
// Libraries (Install via Arduino IDE Library Manager):
//  - ArduinoJson (Benoit Blanchon) v6.x
//  - LiquidCrystal I2C (Frank de Brabander)
//  - DHT sensor library (Adafruit)
//  - Adafruit Unified Sensor
//  - PZEM-004T-v30 (Jakub Mandula)
// ============================================================

#include <WiFi.h>
#include <time.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <PZEM004Tv30.h>

// ============================================================
// KONFIGURASI — WiFi & Firebase
// ============================================================
const char* WIFI_SSID     = "hujan";          // Nama WiFi / Hotspot HP
const char* WIFI_PASSWORD = "02082006";       // Password WiFi
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// ============================================================
// PIN HARDWARE
// ============================================================
#define NUM_SLOTS 3

const int PIN_BTN[NUM_SLOTS]   = {27, 14, 12};  // Tombol Slot 1, 2, 3
const int PIN_LED[NUM_SLOTS]   = {32, 33, 25};  // LED Ring Slot 1, 2, 3
const int PIN_RELAY[NUM_SLOTS] = {23, 19, 18};  // Relay Slot 1, 2, 3
const int PIN_RELAY_SPARE      = 26;            // Relay cadangan (selalu OFF)

#define PIN_DHT    4
#define DHTTYPE    DHT22
#define PZEM_RX    16
#define PZEM_TX    17
#define LCD_ADDR   0x27
#define LCD_COLS   16
#define LCD_ROWS   2

// ============================================================
// PARAMETER OPERASI
// ============================================================
#define OVERHEAT_LIMIT_C     50.0     // Batas suhu darurat (°C) — Matikan semua relay
#define OVERHEAT_SAFE_C      40.0     // Batas suhu pemulihan histeresis (°C) — Kembali ke STANDBY
#define QRIS_TIMEOUT_MS      300000   // Timeout menunggu bayar: 5 menit (ms)

// Interval waktu (ms)
#define INTERVAL_FIREBASE_POLL  2000   // Poll Firebase saat menunggu bayar (ms)
#define INTERVAL_TELEMETRY      5000   // Upload PZEM + DHT22 tiap 5 detik
#define INTERVAL_LCD_REFRESH    500    // Refresh LCD tiap 0.5 detik
#define INTERVAL_BLINK          400    // Kecepatan kedip LED
#define INTERVAL_WIFI_CHECK     30000  // Cek WiFi tiap 30 detik
#define INTERVAL_LCD_ROTATE     3000   // Rotasi tampilan multi-slot tiap 3 detik
#define INTERVAL_DHT_READ       1500   // Sampling DHT22 tiap 1.5 detik

// ============================================================
// OBJEK GLOBAL
// ============================================================
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT dht(PIN_DHT, DHTTYPE);
PZEM004Tv30 pzem(Serial2, PZEM_RX, PZEM_TX);
WiFiClientSecure secureClient;

// ============================================================
// STATE MACHINE PER SLOT
// ============================================================
enum SlotState { STANDBY, WAITING_PAYMENT, ACTIVE, EMERGENCY_STOP };

struct Slot {
  volatile SlotState state  = STANDBY;
  unsigned long waitStart   = 0;       // millis() saat mulai menunggu bayar
  unsigned long activeStart = 0;       // millis() saat relay diaktifkan
  unsigned long durationSec = 0;       // Durasi sewa dalam detik
};
Slot slots[NUM_SLOTS];

// ============================================================
// HARDWARE INTERRUPT UNTUK TOMBOL (Anti-Miss & Early Stop Seketika)
// ============================================================
volatile bool btnTriggered[NUM_SLOTS] = {false, false, false};
volatile unsigned long lastBtnTime[NUM_SLOTS] = {0, 0, 0};
bool lastBtnPhysical[NUM_SLOTS] = {HIGH, HIGH, HIGH};

void IRAM_ATTR isrBtn1() {
  unsigned long now = millis();
  if (now - lastBtnTime[0] > 200) {
    lastBtnTime[0] = now;
    btnTriggered[0] = true;
    if (slots[0].state == ACTIVE) {
      digitalWrite(PIN_RELAY[0], HIGH); // Relay OFF seketika (0ms)
      digitalWrite(PIN_LED[0], LOW);    // LED OFF seketika (0ms)
    }
  }
}

void IRAM_ATTR isrBtn2() {
  unsigned long now = millis();
  if (now - lastBtnTime[1] > 200) {
    lastBtnTime[1] = now;
    btnTriggered[1] = true;
    if (slots[1].state == ACTIVE) {
      digitalWrite(PIN_RELAY[1], HIGH); // Relay OFF seketika (0ms)
      digitalWrite(PIN_LED[1], LOW);    // LED OFF seketika (0ms)
    }
  }
}

void IRAM_ATTR isrBtn3() {
  unsigned long now = millis();
  if (now - lastBtnTime[2] > 200) {
    lastBtnTime[2] = now;
    btnTriggered[2] = true;
    if (slots[2].state == ACTIVE) {
      digitalWrite(PIN_RELAY[2], HIGH); // Relay OFF seketika (0ms)
      digitalWrite(PIN_LED[2], LOW);    // LED OFF seketika (0ms)
    }
  }
}

// ============================================================
// DATA SENSOR TERAKHIR
// ============================================================
float sensorVoltage = 0.0;
float sensorCurrent = 0.0;
float sensorPower   = 0.0;
float sensorEnergy  = 0.0;
float sensorTemp    = 0.0;
float sensorHumid   = 0.0;

// ============================================================
// TIMING & UI
// ============================================================
unsigned long tFirebasePoll = 0;
unsigned long tTelemetry    = 0;
unsigned long tLcdRefresh   = 0;
unsigned long tBlink        = 0;
unsigned long tWifiCheck    = 0;
unsigned long tLcdRotate    = 0;
unsigned long tDhtRead      = 0;
bool blinkState             = false;
bool isOverheated           = false;
int  lcdRotateIdx           = 0;

// Notifikasi sukses bayar di LCD
int      successSlot = -1;
unsigned long tSuccess = 0;
#define SUCCESS_DISPLAY_MS  4000  // Tampilkan "BAYAR BERHASIL" 4 detik

// ============================================================
// DEKLARASI FUNGSI
// ============================================================
void connectWiFi();
void checkWiFi();
void checkButtons();
void pollFirebaseSlots();
void updateTimers();
void updateHardware();
void updateLcdDisplay();
void updateTelemetry();
void handleOverheat();
void setSlotStandby(int idx);
String fbGet(const String& path);
bool fbPut(const String& path, const String& body);
bool fbPatch(const String& path, const String& body);

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n==============================================");
  Serial.println("  SMART PLUG 3-Slot — Firmware Final v1.0");
  Serial.println("==============================================");

  // 1. Init Relay (4 channel) — SEMUA OFF saat boot
  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_RELAY[i], OUTPUT);
    digitalWrite(PIN_RELAY[i], HIGH);  // Active-LOW: HIGH = OFF
  }
  pinMode(PIN_RELAY_SPARE, OUTPUT);
  digitalWrite(PIN_RELAY_SPARE, HIGH);

  // 2. Init Tombol & LED Ring (dengan Hardware Interrupts)
  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_BTN[i], INPUT_PULLUP);
    pinMode(PIN_LED[i], OUTPUT);
    digitalWrite(PIN_LED[i], LOW);
  }
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[0]), isrBtn1, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[1]), isrBtn2, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[2]), isrBtn3, FALLING);

  // 3. Init LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Memulai...    ");

  // 4. Init Sensor
  dht.begin();
  Serial2.begin(9600, SERIAL_8N1, PZEM_RX, PZEM_TX);
  Serial.println("[INIT] DHT22 GPIO4 | PZEM Serial2 RX16/TX17");

  // 5. SSL bypass (dev mode)
  secureClient.setInsecure();

  // 6. Koneksi WiFi
  connectWiFi();

  // 7. Reset semua slot di Firebase ke STANDBY
  for (int i = 0; i < NUM_SLOTS; i++) {
    setSlotStandby(i);
  }

  // 8. Tampilan siap
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print(" Tekan Tombol...");
  Serial.println("\n[SIAP] Sistem dalam mode STANDBY.");
  Serial.println(">> Tekan salah satu dari 3 tombol untuk memulai sewa! <<\n");
}

// ============================================================
// LOOP UTAMA
// ============================================================
void loop() {
  unsigned long now = millis();

  // 1. Proteksi overheat (DHT22)
  handleOverheat();

  // 2. Cek tombol fisik (setiap loop — responsif)
  checkButtons();

  // 3. LED berkedip untuk slot WAITING_PAYMENT
  if (now - tBlink >= INTERVAL_BLINK) {
    tBlink = now;
    blinkState = !blinkState;
  }

  // 4. Poll status slot dari Firebase (adaptif: 2 detik jika waiting payment, 7 detik jika aktif)
  unsigned long currentPollInterval = 7000;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == WAITING_PAYMENT) {
      currentPollInterval = INTERVAL_FIREBASE_POLL;  // 2000 ms
      break;
    }
  }
  if (now - tFirebasePoll >= currentPollInterval) {
    tFirebasePoll = now;
    pollFirebaseSlots();
  }

  // 5. Update countdown timer semua slot
  updateTimers();

  // 6. Update relay & LED berdasarkan state
  updateHardware();

  // 7. Refresh tampilan LCD (tiap 0.5 detik)
  if (now - tLcdRefresh >= INTERVAL_LCD_REFRESH) {
    tLcdRefresh = now;
    updateLcdDisplay();
  }

  // 8. Upload telemetri sensor ke Firebase (tiap 3 detik)
  if (now - tTelemetry >= INTERVAL_TELEMETRY) {
    tTelemetry = now;
    updateTelemetry();
  }

  // 9. Auto-reconnect WiFi (tiap 30 detik)
  if (now - tWifiCheck >= INTERVAL_WIFI_CHECK) {
    tWifiCheck = now;
    checkWiFi();
  }
}

// ============================================================
// WiFi: Koneksi awal
// ============================================================
void connectWiFi() {
  Serial.print("[WiFi] Menghubungkan ke ");
  Serial.println(WIFI_SSID);

  lcd.setCursor(0, 1); lcd.print("WiFi...         ");

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 25) {
    delay(500);
    Serial.print(".");
    retry++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Terhubung! IP: " + WiFi.localIP().toString());
    lcd.setCursor(0, 1); lcd.print("WiFi OK!        ");

    // Sinkronisasi waktu NTP (WIB = UTC+7)
    configTime(7 * 3600, 0, "pool.ntp.org", "time.nist.gov");
    Serial.print("[NTP] Sinkronisasi waktu");
    struct tm timeinfo;
    int ntpRetry = 0;
    while (!getLocalTime(&timeinfo) && ntpRetry < 10) {
      delay(500); Serial.print("."); ntpRetry++;
    }
    Serial.println(ntpRetry < 10 ? " OK!" : " Timeout.");
  } else {
    Serial.println("\n[WiFi] GAGAL! Sistem tetap berjalan offline.");
    lcd.setCursor(0, 1); lcd.print("WiFi GAGAL!     ");
  }
  delay(1000);
}

// ============================================================
// WiFi: Auto-reconnect
// ============================================================
void checkWiFi() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Terputus! Reconnect...");
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int retry = 0;
    while (WiFi.status() != WL_CONNECTED && retry++ < 15) delay(500);
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("[WiFi] Reconnect berhasil!");
    }
  }
}

// ============================================================
// Firebase: GET
// ============================================================
String fbGet(const String& path) {
  if (WiFi.status() != WL_CONNECTED) return "null";
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + "/" + path + ".json");
  http.setTimeout(2500);
  int code = http.GET();
  String res = "null";
  if (code == 200) {
    res = http.getString();
  } else if (code > 0 && code != 200) {
    Serial.printf("[FB GET ERR] %s -> Code: %d\n", path.c_str(), code);
  }
  http.end();
  return res;
}

// ============================================================
// Firebase: PUT
// ============================================================
bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + "/" + path + ".json");
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);
  int code = http.PUT(body);
  if (code != 200) {
    Serial.printf("[FB PUT ERR] %s -> Code: %d\n", path.c_str(), code);
  }
  http.end();
  return (code == 200);
}

// ============================================================
// Firebase: PATCH (Multi-node atomic update dalam 1 request)
// ============================================================
bool fbPatch(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  String url = String(FIREBASE_HOST) + "/" + (path.length() > 0 ? (path + ".json") : ".json");
  http.begin(secureClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);
  int code = http.PATCH(body);
  if (code != 200) {
    Serial.printf("[FB PATCH ERR] Code: %d\n", code);
  }
  http.end();
  return (code == 200);
}

// ============================================================
// Cek Tombol — Deteksi tekan dengan interrupt & edge detection
// ============================================================
void checkButtons() {
  if (isOverheated) return;  // Proteksi: Selama kondisi overheat, tombol fisik dikunci total!

  for (int i = 0; i < NUM_SLOTS; i++) {
    bool raw = digitalRead(PIN_BTN[i]);
    bool pressEvent = false;

    // Prioritas 1: Flag dari hardware interrupt
    if (btnTriggered[i]) {
      btnTriggered[i] = false;
      pressEvent = true;
    }
    // Prioritas 2: Deteksi falling edge langsung (anti-miss)
    else if (lastBtnPhysical[i] == HIGH && raw == LOW) {
      delay(20);
      if (digitalRead(PIN_BTN[i]) == LOW) {
        pressEvent = true;
      }
    }
    lastBtnPhysical[i] = raw;

    if (!pressEvent) continue;

    // --- Aksi berdasarkan state slot saat ini ---
    if (slots[i].state == STANDBY) {
      // Mulai proses sewa — respon hardware INSTAN sebelum kirim internet!
      Serial.printf("\n[BTN %d] Ditekan -> WAITING_PAYMENT\n", i + 1);
      slots[i].state = WAITING_PAYMENT;
      slots[i].waitStart = millis();

      // 1. Update fisik (LED mulai kedip, LCD berganti) SEKETIKA (0ms delay)
      updateHardware();
      updateLcdDisplay();

      // 2. Kirim update ke Firebase tanpa menimpa slot lain
      String slotKey = "slot" + String(i + 1);
      String body = "{\"status\":\"WAITING_PAYMENT\",\"active_duration\":0,\"duration_seconds\":0,\"started_at\":0,\"amount_paid\":0}";
      fbPut(slotKey, body);
      fbPut("slots/" + slotKey, body);
      fbPut("system/active_selection", "{\"slot\":\"" + slotKey + "\",\"status\":\"WAITING_PAYMENT\",\"timestamp\":0}");

      Serial.printf("[FIREBASE] Slot %d -> WAITING_PAYMENT terkirim.\n", i + 1);
    }
    else if (slots[i].state == WAITING_PAYMENT) {
      // Tekan lagi saat menunggu → batalkan seleksi
      Serial.printf("[BTN %d] Batal seleksi.\n", i + 1);
      setSlotStandby(i);
    }
    else if (slots[i].state == ACTIVE) {
      // Early Stop: matikan sewa lebih awal
      Serial.printf("[BTN %d] Early Stop oleh pengguna.\n", i + 1);
      setSlotStandby(i);
    }
  }
}

// ============================================================
// Poll semua slot dari Firebase (1 request batch /slots.json)
// ============================================================
void pollFirebaseSlots() {
  if (isOverheated) return;  // Proteksi: Selama overheat, jangan terima aktivasi slot baru!

  bool hasWaiting = false;
  bool hasActive  = false;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == WAITING_PAYMENT) hasWaiting = true;
    if (slots[i].state == ACTIVE)          hasActive  = true;
  }

  // Jika semua slot STANDBY, hemat jaringan & CPU
  if (!hasWaiting && !hasActive) return;

  // 1 KALI REQUEST: Ambil seluruh data node /slots (hanya ~200ms)
  String raw = fbGet("slots");
  if (raw != "null" && raw.length() >= 10) {
    StaticJsonDocument<1024> doc;
    if (!deserializeJson(doc, raw)) {
      for (int i = 0; i < NUM_SLOTS; i++) {
        String slotKey = "slot" + String(i + 1);
        JsonObject sObj = doc[slotKey];
        if (sObj.isNull()) continue;

        const char* status = sObj["status"];
        if (!status) continue;

        uint32_t durSec = sObj["duration_seconds"] | sObj["active_duration"] | 0;

        // Transisi: WAITING_PAYMENT -> ACTIVE (pembayaran berhasil)
        if (strcmp(status, "ACTIVE") == 0 && slots[i].state != ACTIVE) {
          slots[i].state = ACTIVE;
          slots[i].durationSec = durSec > 0 ? durSec : 900;  // default 15 menit
          slots[i].activeStart = millis();

          // Nyalakan relay & LED
          digitalWrite(PIN_RELAY[i], LOW);   // Active-LOW: LOW = ON
          digitalWrite(PIN_LED[i], HIGH);

          // Trigger notifikasi sukses di LCD
          successSlot = i;
          tSuccess = millis();

          Serial.println("\n=================================================");
          Serial.printf("  PEMBAYARAN DITERIMA! RELAY %d AKTIF!\n", i + 1);
          Serial.printf("  Durasi: %lu detik (%lu menit)\n", slots[i].durationSec, slots[i].durationSec / 60);
          Serial.println("=================================================");
        }

        // Transisi: ACTIVE -> STANDBY (dihentikan dari remote dashboard)
        if (strcmp(status, "STANDBY") == 0 && slots[i].state == ACTIVE) {
          Serial.printf("[REMOTE] Dashboard mematikan Slot %d.\n", i + 1);
          setSlotStandby(i);
        }
      }
    }
  }

  // Fallback cadangan khusus slot yang sedang menunggu bayar
  // Jika webhook Mayar hanya mengupdate root /slotX
  if (hasWaiting) {
    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == WAITING_PAYMENT) {
        String slotKey = "slot" + String(i + 1);
        String rawRoot = fbGet(slotKey);
        if (rawRoot != "null" && rawRoot.length() >= 5) {
          StaticJsonDocument<256> docRoot;
          if (!deserializeJson(docRoot, rawRoot)) {
            const char* st = docRoot["status"];
            if (st && strcmp(st, "ACTIVE") == 0) {
              slots[i].state = ACTIVE;
              uint32_t durSec = docRoot["duration_seconds"] | docRoot["active_duration"] | 0;
              slots[i].durationSec = durSec > 0 ? durSec : 900;
              slots[i].activeStart = millis();

              digitalWrite(PIN_RELAY[i], LOW);
              digitalWrite(PIN_LED[i], HIGH);

              successSlot = i;
              tSuccess = millis();

              Serial.println("\n=================================================");
              Serial.printf("  PEMBAYARAN DITERIMA (ROOT)! RELAY %d AKTIF!\n", i + 1);
              Serial.printf("  Durasi: %lu detik (%lu menit)\n", slots[i].durationSec, slots[i].durationSec / 60);
              Serial.println("=================================================");
            }
          }
        }
      }
    }
  }
}

// ============================================================
// Update countdown timer semua slot
// ============================================================
void updateTimers() {
  if (isOverheated) return; // Thermal Pause: Timer dibekukan selama fase pendinginan overheat

  for (int i = 0; i < NUM_SLOTS; i++) {
    // Cek timeout WAITING_PAYMENT (5 menit)
    if (slots[i].state == WAITING_PAYMENT) {
      if (millis() - slots[i].waitStart >= QRIS_TIMEOUT_MS) {
        Serial.printf("[TIMEOUT] Slot %d: 5 menit tanpa bayar. Reset.\n", i + 1);
        setSlotStandby(i);
      }
    }

    // Cek countdown ACTIVE
    if (slots[i].state == ACTIVE) {
      unsigned long elapsed = (millis() - slots[i].activeStart) / 1000;
      if (elapsed >= slots[i].durationSec) {
        Serial.printf("[SELESAI] Slot %d: Waktu sewa habis!\n", i + 1);
        setSlotStandby(i);
      }
    }
  }
}

// ============================================================
// Update relay & LED ring berdasarkan state
// ============================================================
void updateHardware() {
  if (isOverheated) {
    // Matikan seluruh relay dan seluruh LED seketika
    for (int i = 0; i < NUM_SLOTS; i++) {
      digitalWrite(PIN_RELAY[i], HIGH);
      digitalWrite(PIN_LED[i], LOW);
    }
    digitalWrite(PIN_RELAY_SPARE, HIGH);
    return;
  }

  for (int i = 0; i < NUM_SLOTS; i++) {
    switch (slots[i].state) {
      case ACTIVE:
        digitalWrite(PIN_RELAY[i], LOW);   // Relay ON
        digitalWrite(PIN_LED[i], HIGH);    // LED solid
        break;
      case WAITING_PAYMENT:
        digitalWrite(PIN_RELAY[i], HIGH);  // Relay OFF
        digitalWrite(PIN_LED[i], blinkState ? HIGH : LOW);  // LED berkedip
        break;
      default:  // STANDBY
        digitalWrite(PIN_RELAY[i], HIGH);  // Relay OFF
        digitalWrite(PIN_LED[i], LOW);     // LED mati
        break;
    }
  }
  // Relay spare selalu OFF
  digitalWrite(PIN_RELAY_SPARE, HIGH);
}

// ============================================================
// Helper: Format detik -> "MM:SS"
// ============================================================
void fmtTime(unsigned long totalSec, char* buf, size_t len) {
  unsigned long m = totalSec / 60;
  unsigned long s = totalSec % 60;
  snprintf(buf, len, "%02lu:%02lu", m, s);
}

// ============================================================
// Update LCD 16x2 — Multi-prioritas & multi-slot rotation
// ============================================================
void updateLcdDisplay() {
  char r1[17], r2[17];
  unsigned long now = millis();

  // ── PRIORITAS 0: Kondisi Overheat Darurat (Live Monitoring Suhu) ──
  if (isOverheated) {
    snprintf(r1, 17, "! OVERHEAT >50C!");
    snprintf(r2, 17, "Suhu Box: %4.1f C", sensorTemp);
    lcd.setCursor(0, 0); lcd.print(r1);
    lcd.setCursor(0, 1); lcd.print(r2);
    return;
  }

  // ── PRIORITAS 1: Notifikasi "BAYAR BERHASIL" (4 detik) ──
  if (successSlot >= 0) {
    if (now - tSuccess < SUCCESS_DISPLAY_MS) {
      snprintf(r1, 17, " BAYAR BERHASIL ");
      snprintf(r2, 17, "  SLOT %d AKTIF  ", successSlot + 1);
      lcd.setCursor(0, 0); lcd.print(r1);
      lcd.setCursor(0, 1); lcd.print(r2);
      return;
    }
    successSlot = -1;
  }

  // ── PRIORITAS 2: Slot menunggu pembayaran ──
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == WAITING_PAYMENT) {
      unsigned long elapsed = (now - slots[i].waitStart) / 1000;
      int remaining = max(0, (int)(QRIS_TIMEOUT_MS / 1000) - (int)elapsed);

      snprintf(r1, 17, "Scan QRIS Slot %d", i + 1);
      snprintf(r2, 17, "Tunggu: %3ds    ", remaining);
      lcd.setCursor(0, 0); lcd.print(r1);
      lcd.setCursor(0, 1); lcd.print(r2);
      return;
    }
  }

  // ── PRIORITAS 3: Slot aktif -> Tampilkan Telemetri Sensor Daya PZEM ──
  int activeCount = 0;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == ACTIVE) {
      activeCount++;
    }
  }

  if (activeCount == 0) {
    // Tidak ada slot aktif — IDLE
    snprintf(r1, 17, "  SMART  PLUG   ");
    snprintf(r2, 17, " Tekan Tombol...");
    lcd.setCursor(0, 0); lcd.print(r1);
    lcd.setCursor(0, 1); lcd.print(r2);
    return;
  }

  // Jika ada slot aktif -> Tampilkan data sensor daya bersih & presisi 16 karakter
  // Baris 1: Tegangan (V) & Arus (A)
  snprintf(r1, 17, "V: %3.0fV  I:%4.2fA", sensorVoltage, sensorCurrent);

  // Baris 2: Daya Nyata (Watt)
  snprintf(r2, 17, "Daya: %5.1f Watt", sensorPower);

  lcd.setCursor(0, 0); lcd.print(r1);
  lcd.setCursor(0, 1); lcd.print(r2);
}

// ============================================================
// Baca PZEM-004T + DHT22 & upload ke Firebase /sensors
// ============================================================
void updateTelemetry() {
  // 1. Baca PZEM-004T
  float v = pzem.voltage();
  if (!isnan(v)) {
    sensorVoltage = v;
    float i = pzem.current();  if (!isnan(i)) sensorCurrent = i;
    float p = pzem.power();    if (!isnan(p)) sensorPower   = p;
    float e = pzem.energy();   if (!isnan(e)) sensorEnergy  = e;

    Serial.printf("[PZEM OK] %.1fV | %.2fA | %.1fW | %.4f kWh\n",
                  sensorVoltage, sensorCurrent, sensorPower, sensorEnergy);
  } else {
    Serial.println("[PZEM] Tidak ada respons (NAN). Cek steker 220V & kabel.");
  }

  // 2. Baca DHT22
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (!isnan(t)) sensorTemp  = t;
  if (!isnan(h)) sensorHumid = h;

  // 3. Upload ke Firebase /sensors (node tunggal gabungan)
  if (WiFi.status() == WL_CONNECTED) {
    String json = "{";
    json += "\"voltage\":"     + String(sensorVoltage, 1) + ",";
    json += "\"current\":"     + String(sensorCurrent, 2) + ",";
    json += "\"power\":"       + String(sensorPower, 1) + ",";
    json += "\"energy\":"      + String(sensorEnergy, 4) + ",";
    json += "\"temperature\":" + String(sensorTemp, 1) + ",";
    json += "\"humidity\":"    + String(sensorHumid, 1) + ",";
    json += "\"updated_at\":"  + String((unsigned long)time(nullptr));
    json += "}";

    bool ok = fbPut("sensors", json);
    if (ok) {
      Serial.println("[FIREBASE] Telemetri sensor berhasil di-update!");
    } else {
      Serial.println("[FIREBASE] GAGAL upload telemetri! Pastikan Rules Firebase aktif.");
    }
  }
}

// ============================================================
// Proteksi Overheat (>60°C) dengan Histeresis Termal (<50°C)
// ============================================================
void handleOverheat() {
  unsigned long now = millis();

  // 1. Sampling DHT22 tiap 1.5 detik untuk deteksi & monitoring live
  if (now - tDhtRead >= INTERVAL_DHT_READ) {
    tDhtRead = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t)) sensorTemp  = t;
    if (!isnan(h)) sensorHumid = h;
  }

  // 2. FASE 1: Suhu >= 50°C -> AKTIFKAN EMERGENCY STOP & THERMAL PAUSE
  if (!isOverheated && sensorTemp >= OVERHEAT_LIMIT_C) {
    isOverheated = true;

    Serial.println("\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
    Serial.printf("  DARURAT: SUHU %.1f°C >= 50°C — EMERGENCY STOP!\n", sensorTemp);
    Serial.println("  Seluruh relay & LED dimatikan! Tombol dikunci.");
    Serial.println("  Membekukan (PAUSE) sisa durasi sewa pengguna...");
    Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");

    // Matikan seluruh relay beban (Active-LOW: HIGH = OFF) & LED seketika
    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == ACTIVE) {
        unsigned long elapsed = (millis() - slots[i].activeStart) / 1000;
        if (elapsed < slots[i].durationSec) {
          slots[i].durationSec -= elapsed; // Simpan sisa detik
        } else {
          slots[i].durationSec = 0;
        }
        Serial.printf("[THERMAL PAUSE] Slot %d dijeda. Sisa durasi: %lu detik.\n", i + 1, slots[i].durationSec);
      } else {
        slots[i].durationSec = 0;
      }

      slots[i].state = EMERGENCY_STOP;
      digitalWrite(PIN_RELAY[i], HIGH);
      digitalWrite(PIN_LED[i], LOW);
    }
    digitalWrite(PIN_RELAY_SPARE, HIGH);

    // Kirim notifikasi darurat ke Firebase
    fbPut("system/emergency_alert", "true");
    for (int i = 0; i < NUM_SLOTS; i++) {
      String slotKey = "slot" + String(i + 1);
      fbPut("slots/" + slotKey + "/status", "\"EMERGENCY_STOP\"");
      fbPut(slotKey + "/status", "\"EMERGENCY_STOP\"");
    }
  }
  // 3. FASE 2: Histeresis Termal (Suhu Turun < 40°C) -> AUTO RECOVERY & THERMAL RESUME
  else if (isOverheated && sensorTemp > 0 && sensorTemp < OVERHEAT_SAFE_C) {
    isOverheated = false;

    Serial.println("\n============================================");
    Serial.printf("  SUHU SUDAH AMAN (%.1f°C < 40°C) — AUTO RECOVERY!\n", sensorTemp);
    Serial.println("  Melanjutkan sesi pengguna aktif & stand-by slot kosong...");
    Serial.println("============================================");

    // Bersihkan alert di Firebase
    fbPut("system/emergency_alert", "false");

    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].durationSec > 0) {
        // RESUME: Melanjutkan sisa waktu sewa pengguna
        slots[i].state = ACTIVE;
        slots[i].activeStart = millis(); // Mulai timer dari sisa detik tersimpan
        digitalWrite(PIN_RELAY[i], LOW);  // Relay ON kembali
        digitalWrite(PIN_LED[i], HIGH); // LED solid ON
        Serial.printf("[THERMAL RESUME] Slot %d aktif kembali! Sisa waktu: %lu detik.\n", i + 1, slots[i].durationSec);

        String slotKey = "slot" + String(i + 1);
        fbPut("slots/" + slotKey + "/status", "\"ACTIVE\"");
        fbPut("slots/" + slotKey + "/duration_seconds", String(slots[i].durationSec));
        fbPut(slotKey + "/status", "\"ACTIVE\"");
      } else {
        // Tidak ada sewa aktif sebelumnya -> Reset ke STANDBY
        setSlotStandby(i);
      }
    }
  }
}

// ============================================================
// Reset slot ke STANDBY (lokal + Firebase dual-node)
// ============================================================
void setSlotStandby(int idx) {
  slots[idx].state = STANDBY;
  slots[idx].durationSec = 0;

  // Hardware: relay OFF, LED OFF seketika
  digitalWrite(PIN_RELAY[idx], HIGH);
  digitalWrite(PIN_LED[idx], LOW);
  updateHardware();
  updateLcdDisplay();

  if (WiFi.status() != WL_CONNECTED) return;

  String slotKey = "slot" + String(idx + 1);
  String body = "{\"status\":\"STANDBY\",\"duration_seconds\":0,\"active_duration\":0,\"amount_paid\":0,\"nominal_paid\":0,\"started_at\":0,\"activated_at\":0,\"expires_at\":0}";
  fbPut(slotKey, body);
  fbPut("slots/" + slotKey, body);
  fbPut("system/active_selection", "{\"slot\":\"none\",\"status\":\"IDLE\",\"timestamp\":0}");

  Serial.printf("[SLOT %d] Reset -> STANDBY terkirim.\n", idx + 1);
}
