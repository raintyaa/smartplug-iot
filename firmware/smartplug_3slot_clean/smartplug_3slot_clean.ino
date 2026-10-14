// ============================================================
// SMART PLUG 3-Slot — Firmware Final (Production Clean)
// Dual-Run: Firebase RTDB + EMQX Cloud MQTTS (TLS Port 8883)
// Fitur:
// - 3 Slot Broco Relay (Active-LOW) & Metal Buttons (50ms Debounce)
// - Sensor PZEM-004T v3.0 (UART2 RX16/TX17) & DHT22 (GPIO 4)
// - LCD 16x2 I2C (0x27)
// - Proteksi Termal Histeresis (Overheat >= 42°C, Resume < 37°C)
// - Mode Sewa Otomatis (Durasi Countdown via Webhook Mayar)
// - Mode Admin Bypass Kontinu (Nyalakan Manual tanpa batas waktu)
// - Streaming Telemetri Realtime MQTTS ke EMQX Cloud
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
#include <PubSubClient.h>

// ============================================================
// 1. KONFIGURASI WIFI & FIREBASE
// ============================================================
const char* WIFI_SSID     = "hujan";
const char* WIFI_PASSWORD = "02082006";
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// ============================================================
// 2. KONFIGURASI MQTT (EMQX Cloud Serverless)
// ============================================================
#define ENABLE_MQTT          true
const char* MQTT_BROKER      = "o2f6eb8b.ala.asia-southeast1.emqxsl.com";
const int   MQTT_PORT        = 8883;             // MQTTS TLS/SSL
const char* MQTT_USER        = "ranova_esp32";
const char* MQTT_PASSWORD    = "02082006";       // Password autentikasi EMQX
const char* MQTT_CLIENT_ID   = "ranova-esp32-node1";

const char* TOPIC_TELEMETRY  = "ranova/device_001/telemetry";
const char* TOPIC_COMMAND    = "ranova/device_001/command";
const char* TOPIC_STATUS     = "ranova/device_001/status";

// ============================================================
// 3. PIN HARDWARE
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
// 4. PARAMETER OPERASI
// ============================================================
#define OVERHEAT_LIMIT_C     42.0     // Suhu kritis pemutus darurat (°C)
#define OVERHEAT_SAFE_C      37.0     // Suhu pemulihan histeresis (°C)
#define QRIS_TIMEOUT_MS      300000   // Timeout bayar: 5 menit (300.000 ms)

#define INTERVAL_FIREBASE_POLL  2000  // Polling Firebase saat menunggu bayar
#define INTERVAL_TELEMETRY      5000  // Upload PZEM + DHT22 tiap 5 detik
#define INTERVAL_LCD_REFRESH    500   // Refresh LCD tiap 0.5 detik
#define INTERVAL_BLINK          400   // Kecepatan kedip LED saat menunggu bayar
#define INTERVAL_WIFI_CHECK     30000 // Cek koneksi WiFi tiap 30 detik
#define INTERVAL_DHT_READ       1500  // Sampling DHT22 tiap 1.5 detik
#define DEBOUNCE_DELAY_MS       50    // Debounce tombol fisik software 50ms

// ============================================================
// 5. OBJEK GLOBAL
// ============================================================
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT dht(PIN_DHT, DHTTYPE);
PZEM004Tv30 pzem(Serial2, PZEM_RX, PZEM_TX);
WiFiClientSecure secureClient;

#if ENABLE_MQTT
WiFiClientSecure mqttNetClient;
PubSubClient mqttClient(mqttNetClient);
unsigned long lastMqttReconnect = 0;
#endif

enum SlotState { STANDBY, WAITING_PAYMENT, ACTIVE, EMERGENCY_STOP };

struct Slot {
  volatile SlotState state  = STANDBY;
  unsigned long waitStart   = 0;
  unsigned long activeStart = 0;
  unsigned long durationSec = 0; // 0 = Mode manual tanpa batas waktu (kontinu)
};
Slot slots[NUM_SLOTS];

unsigned long lastBtnDebounceTime[NUM_SLOTS] = {0, 0, 0};
bool lastBtnPhysical[NUM_SLOTS]              = {HIGH, HIGH, HIGH};
bool btnState[NUM_SLOTS]                     = {HIGH, HIGH, HIGH};

float sensorVoltage = 0.0;
float sensorCurrent = 0.0;
float sensorPower   = 0.0;
float sensorEnergy  = 0.0;
float sensorTemp    = 0.0;
float sensorHumid   = 0.0;

unsigned long tFirebasePoll = 0;
unsigned long tTelemetry    = 0;
unsigned long tLcdRefresh   = 0;
unsigned long tBlink        = 0;
unsigned long tWifiCheck    = 0;
unsigned long tDhtRead      = 0;
bool blinkState             = false;
bool isOverheated           = false;

int           successSlot = -1;
unsigned long tSuccess    = 0;
#define SUCCESS_DISPLAY_MS 3500

// Deklarasi Fungsi
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
void publishSlotState(int idx);
String fbGet(const String& path);
bool fbPut(const String& path, const String& body);
bool fbPatch(const String& path, const String& body);

#if ENABLE_MQTT
void mqttCallback(char* topic, byte* payload, unsigned int length);
#endif

// ============================================================
// 6. SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n==============================================");
  Serial.println("  RANOVA Smart Plug 3-Slot — Hybrid MQTT v2.0 ");
  Serial.println("==============================================");

  // Inisialisasi Relay (Active-LOW, Default OFF = HIGH)
  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_RELAY[i], OUTPUT);
    digitalWrite(PIN_RELAY[i], HIGH);
  }
  pinMode(PIN_RELAY_SPARE, OUTPUT);
  digitalWrite(PIN_RELAY_SPARE, HIGH);

  // Inisialisasi Tombol & LED Ring
  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_BTN[i], INPUT_PULLUP);
    pinMode(PIN_LED[i], OUTPUT);
    digitalWrite(PIN_LED[i], LOW);
  }

  // Inisialisasi LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Memulai...    ");

  // Inisialisasi Sensor
  dht.begin();
  Serial2.begin(9600, SERIAL_8N1, PZEM_RX, PZEM_TX);
  Serial.println("[INIT] DHT22 GPIO4 | PZEM Serial2 RX16/TX17");

  // Konfigurasi TLS Client
  secureClient.setInsecure();

#if ENABLE_MQTT
  mqttNetClient.setInsecure();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512); // Buffer lega untuk JSON telemetri
#endif

  connectWiFi();

  // Reset seluruh slot ke STANDBY
  for (int i = 0; i < NUM_SLOTS; i++) {
    setSlotStandby(i);
  }

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print(" Tekan Tombol...");
  Serial.println("\n[SIAP] Sistem dalam mode STANDBY.");
  Serial.println(">> Tekan salah satu dari 3 tombol untuk memulai sewa! <<\n");
}

// ============================================================
// 7. LOOP UTAMA
// ============================================================
void loop() {
  unsigned long now = millis();

  // 1. Proteksi Overheat (Prioritas 0)
  handleOverheat();

  // 2. Pembacaan Tombol Fisik (Debounced 50ms)
  checkButtons();

  // 3. Kedip LED Ring saat WAITING_PAYMENT
  if (now - tBlink >= INTERVAL_BLINK) {
    tBlink = now;
    blinkState = !blinkState;
  }

  // 4. Polling Firebase RTDB
  unsigned long currentPollInterval = 7000;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == WAITING_PAYMENT) {
      currentPollInterval = INTERVAL_FIREBASE_POLL;
      break;
    }
  }
  if (now - tFirebasePoll >= currentPollInterval) {
    tFirebasePoll = now;
    pollFirebaseSlots();
  }

  // 5. Update Timer & Countdown Slot
  updateTimers();

  // 6. Update Fisik Hardware (Relay & LED Ring)
  updateHardware();

  // 7. Refresh Tampilan LCD 16x2
  if (now - tLcdRefresh >= INTERVAL_LCD_REFRESH) {
    tLcdRefresh = now;
    updateLcdDisplay();
  }

  // 8. Upload Telemetri Sensor (Firebase + MQTT)
  if (now - tTelemetry >= INTERVAL_TELEMETRY) {
    tTelemetry = now;
    updateTelemetry();
  }

  // 9. Cek Rutin Koneksi WiFi
  if (now - tWifiCheck >= INTERVAL_WIFI_CHECK) {
    tWifiCheck = now;
    checkWiFi();
  }

#if ENABLE_MQTT
  // 10. Manajemen Koneksi MQTT (Non-Blocking)
  if (WiFi.status() == WL_CONNECTED) {
    if (!mqttClient.connected()) {
      if (now - lastMqttReconnect >= 7000) {
        lastMqttReconnect = now;
        Serial.print("[MQTT] Menghubungkan ke EMQX Cloud...");
        if (mqttClient.connect(MQTT_CLIENT_ID, MQTT_USER, MQTT_PASSWORD, TOPIC_STATUS, 1, true, "{\"state\":\"offline\"}")) {
          Serial.println(" OK! Terhubung.");
          mqttClient.subscribe(TOPIC_COMMAND);
          mqttClient.publish(TOPIC_STATUS, "{\"state\":\"online\"}", true);
          for (int i = 0; i < NUM_SLOTS; i++) publishSlotState(i);
        } else {
          Serial.printf(" Gagal (rc=%d)\n", mqttClient.state());
        }
      }
    } else {
      mqttClient.loop();
    }
  }
#endif
}

// ============================================================
// 8. KONEKSI & REKONEKSI WIFI
// ============================================================
void connectWiFi() {
  Serial.print("\n[WiFi] Menghubungkan ke ");
  Serial.println(WIFI_SSID);

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("Connect WiFi...");
  lcd.setCursor(0, 1); lcd.print(WIFI_SSID);

  WiFi.mode(WIFI_STA);
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
// 9. HTTP REST FIREBASE HELPER
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
  }
  http.end();
  return res;
}

bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + "/" + path + ".json");
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);
  int code = http.PUT(body);
  http.end();
  return (code == 200);
}

bool fbPatch(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  String url = String(FIREBASE_HOST) + "/" + (path.length() > 0 ? (path + ".json") : ".json");
  http.begin(secureClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);
  int code = http.PATCH(body);
  http.end();
  return (code == 200);
}

// ============================================================
// 10. PEMBACAAN TOMBOL FISIK (50ms Software Debounce)
// ============================================================
void checkButtons() {
  if (isOverheated) return;

  unsigned long now = millis();

  for (int i = 0; i < NUM_SLOTS; i++) {
    bool raw = digitalRead(PIN_BTN[i]);

    if (raw != lastBtnPhysical[i]) {
      lastBtnDebounceTime[i] = now;
    }
    lastBtnPhysical[i] = raw;

    if ((now - lastBtnDebounceTime[i]) > DEBOUNCE_DELAY_MS) {
      if (raw != btnState[i]) {
        btnState[i] = raw;

        if (btnState[i] == LOW) {
          if (slots[i].state == STANDBY) {
            Serial.printf("\n[BTN %d] Ditekan -> WAITING_PAYMENT\n", i + 1);
            slots[i].state = WAITING_PAYMENT;
            slots[i].waitStart = millis();

            updateHardware();
            updateLcdDisplay();
            publishSlotState(i);

            String slotKey = "slot" + String(i + 1);
            String body = "{\"status\":\"WAITING_PAYMENT\",\"active_duration\":0,\"duration_seconds\":0,\"started_at\":0,\"amount_paid\":0}";
            fbPut(slotKey, body);
            fbPut("slots/" + slotKey, body);
            fbPut("system/active_selection", "{\"slot\":\"" + slotKey + "\",\"status\":\"WAITING_PAYMENT\",\"timestamp\":0}");

            Serial.printf("[FIREBASE] Slot %d -> WAITING_PAYMENT terkirim.\n", i + 1);
          }
          else if (slots[i].state == WAITING_PAYMENT) {
            Serial.printf("[BTN %d] Batal seleksi.\n", i + 1);
            setSlotStandby(i);
          }
          else if (slots[i].state == ACTIVE) {
            Serial.printf("[BTN %d] Early Stop oleh pengguna.\n", i + 1);
            setSlotStandby(i);
          }
        }
      }
    }
  }
}

// ============================================================
// 11. POLLING STATUS FIREBASE
// ============================================================
void pollFirebaseSlots() {
  if (isOverheated) return;

  bool hasWaiting = false;
  bool hasActive  = false;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == WAITING_PAYMENT) hasWaiting = true;
    if (slots[i].state == ACTIVE)          hasActive  = true;
  }

  if (!hasWaiting && !hasActive) return;

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

        uint32_t durSec  = sObj["duration_seconds"] | sObj["active_duration"] | 0;
        const char* mode = sObj["mode"];

        // Transisi ke ACTIVE (Pembayaran Webhook Mayar ATAU Bypass Admin)
        if (strcmp(status, "ACTIVE") == 0 && slots[i].state != ACTIVE) {
          slots[i].state = ACTIVE;

          // Mode Kontinu (Admin Bypass tanpa batas waktu) vs Mode Sewa Bertarif
          if ((mode != nullptr && strcmp(mode, "MANUAL_OVERRIDE") == 0) || durSec == 0) {
            slots[i].durationSec = 0; // 0 = Kontinu (tanpa batas durasi)
          } else {
            slots[i].durationSec = durSec > 0 ? durSec : 900;
          }
          slots[i].activeStart = millis();

          digitalWrite(PIN_RELAY[i], LOW);
          digitalWrite(PIN_LED[i], HIGH);

          successSlot = i;
          tSuccess = millis();
          publishSlotState(i);

          Serial.println("\n=================================================");
          if (slots[i].durationSec == 0) {
            Serial.printf("  ADMIN BYPASS AKTIF! RELAY %d ON (KONTINU)!\n", i + 1);
          } else {
            Serial.printf("  PEMBAYARAN DITERIMA! RELAY %d AKTIF!\n", i + 1);
            Serial.printf("  Durasi: %lu detik (%lu menit)\n", slots[i].durationSec, slots[i].durationSec / 60);
          }
          Serial.println("=================================================");
        }

        // Transisi ke STANDBY (Remote Stop oleh Dashboard Web)
        if (strcmp(status, "STANDBY") == 0 && slots[i].state == ACTIVE) {
          Serial.printf("[REMOTE] Dashboard mematikan Slot %d.\n", i + 1);
          setSlotStandby(i);
        }
      }
    }
  }

  // Fallback kompatibilitas node root (/slot1, /slot2, /slot3)
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
              const char* md = docRoot["mode"];

              if ((md != nullptr && strcmp(md, "MANUAL_OVERRIDE") == 0) || durSec == 0) {
                slots[i].durationSec = 0;
              } else {
                slots[i].durationSec = durSec > 0 ? durSec : 900;
              }
              slots[i].activeStart = millis();

              digitalWrite(PIN_RELAY[i], LOW);
              digitalWrite(PIN_LED[i], HIGH);

              successSlot = i;
              tSuccess = millis();
              publishSlotState(i);

              Serial.println("\n=================================================");
              Serial.printf("  PEMBAYARAN DITERIMA (ROOT)! RELAY %d AKTIF!\n", i + 1);
              Serial.println("=================================================");
            }
          }
        }
      }
    }
  }
}

// ============================================================
// 12. UPDATE TIMERS & HITUNG MUNDUR SEWA
// ============================================================
void updateTimers() {
  if (isOverheated) return;

  for (int i = 0; i < NUM_SLOTS; i++) {
    // Timeout 5 menit saat menunggu pembayaran QRIS
    if (slots[i].state == WAITING_PAYMENT) {
      if (millis() - slots[i].waitStart >= QRIS_TIMEOUT_MS) {
        Serial.printf("[TIMEOUT] Slot %d: 5 menit tanpa bayar. Reset.\n", i + 1);
        setSlotStandby(i);
      }
    }

    // Countdown sewa: HANYA jika durationSec > 0
    if (slots[i].state == ACTIVE) {
      if (slots[i].durationSec > 0) {
        unsigned long elapsed = (millis() - slots[i].activeStart) / 1000;
        if (elapsed >= slots[i].durationSec) {
          Serial.printf("[SELESAI] Slot %d: Waktu sewa habis!\n", i + 1);
          setSlotStandby(i);
        }
      }
      // Jika durationSec == 0 (Mode Bypass Kontinu): Relay tetap ON tanpa batas waktu!
    }
  }
}

// ============================================================
// 13. KONTROL RELAY & LED RING FISIK
// ============================================================
void updateHardware() {
  if (isOverheated) {
    for (int i = 0; i < NUM_SLOTS; i++) {
      digitalWrite(PIN_RELAY[i], HIGH);
      digitalWrite(PIN_LED[i], LOW);
    }
    return;
  }

  for (int i = 0; i < NUM_SLOTS; i++) {
    switch (slots[i].state) {
      case STANDBY:
        digitalWrite(PIN_RELAY[i], HIGH);
        digitalWrite(PIN_LED[i], LOW);
        break;
      case WAITING_PAYMENT:
        digitalWrite(PIN_RELAY[i], HIGH);
        digitalWrite(PIN_LED[i], blinkState ? HIGH : LOW);
        break;
      case ACTIVE:
        digitalWrite(PIN_RELAY[i], LOW);
        digitalWrite(PIN_LED[i], HIGH);
        break;
      case EMERGENCY_STOP:
        digitalWrite(PIN_RELAY[i], HIGH);
        digitalWrite(PIN_LED[i], LOW);
        break;
    }
  }
}

// ============================================================
// 14. TAMPILAN LCD 16x2 I2C
// ============================================================
void updateLcdDisplay() {
  char r1[17], r2[17];
  unsigned long now = millis();

  // 1. Kondisi Overheat (Prioritas 0)
  if (isOverheated) {
    snprintf(r1, 17, "! OVERHEAT >%dC!", (int)OVERHEAT_LIMIT_C);
    snprintf(r2, 17, "Suhu Box: %4.1f C", sensorTemp);
    lcd.setCursor(0, 0); lcd.print(r1);
    lcd.setCursor(0, 1); lcd.print(r2);
    return;
  }

  // 2. Banner Notifikasi Sukses Aktivasi
  if (successSlot >= 0) {
    if (now - tSuccess < SUCCESS_DISPLAY_MS) {
      if (slots[successSlot].durationSec == 0) {
        snprintf(r1, 17, "  MANUAL BYPASS ");
        snprintf(r2, 17, " SLOT %d ON (INF)", successSlot + 1);
      } else {
        snprintf(r1, 17, " BAYAR BERHASIL ");
        snprintf(r2, 17, "  SLOT %d AKTIF  ", successSlot + 1);
      }
      lcd.setCursor(0, 0); lcd.print(r1);
      lcd.setCursor(0, 1); lcd.print(r2);
      return;
    }
    successSlot = -1;
  }

  // 3. Tampilan Menunggu Pembayaran QRIS
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

  // 4. Tampilan Slot Aktif (Telemetri Daya PZEM-004T)
  int activeCount = 0;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == ACTIVE) activeCount++;
  }

  if (activeCount == 0) {
    snprintf(r1, 17, "  SMART  PLUG   ");
    snprintf(r2, 17, " Tekan Tombol...");
    lcd.setCursor(0, 0); lcd.print(r1);
    lcd.setCursor(0, 1); lcd.print(r2);
    return;
  }

  snprintf(r1, 17, "V: %3.0fV  I:%4.2fA", sensorVoltage, sensorCurrent);
  snprintf(r2, 17, "Daya: %5.1f Watt", sensorPower);
  lcd.setCursor(0, 0); lcd.print(r1);
  lcd.setCursor(0, 1); lcd.print(r2);
}

// ============================================================
// 15. BACA SENSOR & UPLOAD TELEMETRI (Dual-Run: Firebase + MQTT)
// ============================================================
void updateTelemetry() {
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

  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (!isnan(t)) sensorTemp  = t;
  if (!isnan(h)) sensorHumid = h;

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

    // Upload ke Firebase RTDB
    fbPut("sensors", json);

#if ENABLE_MQTT
    // Publish ke EMQX Cloud Broker secara paralel
    if (mqttClient.connected()) {
      mqttClient.publish(TOPIC_TELEMETRY, json.c_str());
    }
#endif
  }
}

// ============================================================
// 16. PROTEKSI OVERHEAT (>42°C) DENGAN PENDINGINAN (<37°C)
// ============================================================
void handleOverheat() {
  unsigned long now = millis();

  if (now - tDhtRead >= INTERVAL_DHT_READ) {
    tDhtRead = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t)) sensorTemp  = t;
    if (!isnan(h)) sensorHumid = h;
  }

  // Pemicu Darurat: Suhu mencapai >= OVERHEAT_LIMIT_C (42.0°C)
  if (!isOverheated && sensorTemp >= OVERHEAT_LIMIT_C) {
    isOverheated = true;

    Serial.println("\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
    Serial.printf("  ALARM OVERHEAT! Suhu: %.1f°C >= %.1f°C!\n", sensorTemp, OVERHEAT_LIMIT_C);
    Serial.println("  THERMAL PAUSE: Mematikan semua relay seketika!");
    Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n");

    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == ACTIVE) {
        if (slots[i].durationSec > 0) {
          unsigned long elapsed = (now - slots[i].activeStart) / 1000;
          if (elapsed < slots[i].durationSec) {
            slots[i].durationSec -= elapsed; // Sisa durasi dibekukan
          } else {
            slots[i].durationSec = 0;
          }
        }
        publishSlotState(i);
      }
    }

    for (int i = 0; i < NUM_SLOTS; i++) {
      digitalWrite(PIN_RELAY[i], HIGH);
      digitalWrite(PIN_LED[i], LOW);
    }

    fbPut("system/emergency_alert", "true");
    updateLcdDisplay();
  }
  // Pemulihan Otomatis: Suhu turun ke < OVERHEAT_SAFE_C (37.0°C)
  else if (isOverheated && sensorTemp > 0 && sensorTemp < OVERHEAT_SAFE_C) {
    isOverheated = false;

    Serial.println("\n============================================");
    Serial.printf("  SUHU SUDAH AMAN (%.1f°C < %.1f°C) — AUTO RECOVERY!\n", sensorTemp, OVERHEAT_SAFE_C);
    Serial.println("============================================");

    fbPut("system/emergency_alert", "false");

    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].durationSec > 0) {
        slots[i].state = ACTIVE;
        slots[i].activeStart = millis();
        digitalWrite(PIN_RELAY[i], LOW);
        digitalWrite(PIN_LED[i], HIGH);
        Serial.printf("[THERMAL RESUME] Slot %d aktif kembali! Sisa: %lu dtk.\n", i + 1, slots[i].durationSec);
        publishSlotState(i);

        String slotKey = "slot" + String(i + 1);
        fbPut("slots/" + slotKey + "/status", "\"ACTIVE\"");
        fbPut("slots/" + slotKey + "/duration_seconds", String(slots[i].durationSec));
        fbPut(slotKey + "/status", "\"ACTIVE\"");
      } else {
        setSlotStandby(i);
      }
    }
  }
}

// ============================================================
// 17. RESET SLOT KE STANDBY
// ============================================================
void setSlotStandby(int idx) {
  slots[idx].state = STANDBY;
  slots[idx].durationSec = 0;

  digitalWrite(PIN_RELAY[idx], HIGH);
  digitalWrite(PIN_LED[idx], LOW);
  updateHardware();
  updateLcdDisplay();
  publishSlotState(idx);

  if (WiFi.status() != WL_CONNECTED) return;

  String slotKey = "slot" + String(idx + 1);
  String body = "{\"status\":\"STANDBY\",\"duration_seconds\":0,\"active_duration\":0,\"amount_paid\":0,\"nominal_paid\":0,\"started_at\":0,\"activated_at\":0,\"expires_at\":0}";
  fbPut(slotKey, body);
  fbPut("slots/" + slotKey, body);
  fbPut("system/active_selection", "{\"slot\":\"none\",\"status\":\"IDLE\",\"timestamp\":0}");

  Serial.printf("[SLOT %d] Reset -> STANDBY terkirim.\n", idx + 1);
}

// ============================================================
// 18. BROADCAST STATUS SLOT KE MQTT
// ============================================================
void publishSlotState(int idx) {
#if ENABLE_MQTT
  if (!mqttClient.connected()) return;

  StaticJsonDocument<128> doc;
  doc["slot"] = idx + 1;
  const char* stStr = "STANDBY";
  if (slots[idx].state == ACTIVE) stStr = "ACTIVE";
  else if (slots[idx].state == WAITING_PAYMENT) stStr = "WAITING_PAYMENT";
  else if (slots[idx].state == EMERGENCY_STOP) stStr = "EMERGENCY_STOP";

  doc["status"]       = stStr;
  doc["duration_sec"] = slots[idx].durationSec;

  if (slots[idx].state == ACTIVE && slots[idx].durationSec > 0) {
    unsigned long elapsed = (millis() - slots[idx].activeStart) / 1000;
    doc["remaining_sec"] = (elapsed < slots[idx].durationSec) ? (slots[idx].durationSec - elapsed) : 0;
  } else {
    doc["remaining_sec"] = 0;
  }

  char buf[128];
  serializeJson(doc, buf);
  String top = "ranova/device_001/slots/" + String(idx + 1) + "/state";
  mqttClient.publish(top.c_str(), buf, true);
#endif
}

// ============================================================
// 19. HANDLER PERINTAH MQTT MASUK (Downlink Control)
// ============================================================
#if ENABLE_MQTT
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg = "";
  for (unsigned int i = 0; i < length; i++) msg += (char)payload[i];
  Serial.printf("\n[MQTT RX] Topik: %s | Payload: %s\n", topic, msg.c_str());

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, msg);
  String action = "";
  int targetSlot = 0;

  if (!err) {
    action = doc["action"] | "";
    targetSlot = doc["slot"] | 0;
  } else {
    if (msg.startsWith("START_")) {
      action = "START";
      targetSlot = msg.substring(6).toInt();
    } else if (msg.startsWith("STOP_")) {
      action = "STOP";
      targetSlot = msg.substring(5).toInt();
    }
  }

  // Perintah Aktivasi Manual (Admin Bypass Tanpa Batas Waktu)
  if (action == "START" && targetSlot >= 1 && targetSlot <= NUM_SLOTS) {
    int idx = targetSlot - 1;
    if (!isOverheated) {
      slots[idx].state = ACTIVE;
      slots[idx].durationSec = 0; // Mode kontinu
      slots[idx].activeStart = millis();

      digitalWrite(PIN_RELAY[idx], LOW);
      digitalWrite(PIN_LED[idx], HIGH);

      successSlot = idx;
      tSuccess = millis();
      publishSlotState(idx);

      // Sinkronkan ke Firebase agar dashboard web juga ter-update seketika
      String slotKey = "slot" + String(targetSlot);
      String body = "{\"status\":\"ACTIVE\",\"active_duration\":0,\"duration_seconds\":0,\"mode\":\"MANUAL_OVERRIDE\"}";
      fbPatch(slotKey, body);
      fbPatch("slots/" + slotKey, body);

      Serial.printf("[MQTT CMD] Slot %d BERHASIL DIAKTIFKAN (Mode Kontinu)!\n", targetSlot);
    }
  }
  // Perintah Matikan Manual
  else if (action == "STOP" && targetSlot >= 1 && targetSlot <= NUM_SLOTS) {
    int idx = targetSlot - 1;
    setSlotStandby(idx);
    publishSlotState(idx);
    Serial.printf("[MQTT CMD] Slot %d BERHASIL DIMATIKAN!\n", targetSlot);
  }
}
#endif
