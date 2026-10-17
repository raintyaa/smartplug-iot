// ============================================================
// SMART PLUG 3-Slot — Firmware Final (Production Full-MQTT)
// Arsitektur: 100% MQTT via EMQX Cloud Serverless (MQTTS TLS 8883)
// FreeRTOS Dual-Core Architecture:
// - Core 0: Task UI Independen (LCD 16x2 I2C, LED Ring Blinking, Hardware Debounce)
// - Core 1: MQTT Client, PZEM-004T Serial2, DHT22, State Machine & Thermal Protection
// Fitur:
// - 3 Slot Broco Relay (Active-LOW) & Metal Buttons (50ms Debounce)
// - Sensor PZEM-004T v3.0 (UART2 RX16/TX17) & DHT22 (GPIO 4)
// - LCD 16x2 I2C (0x27)
// - Proteksi Termal Histeresis (Overheat >= 42°C, Resume < 37°C) dengan Bekukan Durasi
// - Mode Sewa Otomatis (Durasi Countdown via Downlink MQTT Webhook Mayar)
// - Mode Admin Bypass Kontinu (durationSec == 0, tanpa batas waktu)
// - MQTT Last Will & Testament (LWT) untuk Deteksi Steker Dicabut Seketika
// ============================================================

#include <WiFi.h>
#include <time.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <PZEM004Tv30.h>
#include <PubSubClient.h>
#include <HTTPClient.h>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>

// ============================================================
// 1. KONFIGURASI WIFI & MQTT (EMQX Cloud Serverless)
// ============================================================
const char* WIFI_SSID     = "hujan";
const char* WIFI_PASSWORD = "02082006";

const char* MQTT_BROKER   = "o2f6eb8b.ala.asia-southeast1.emqxsl.com";
const int   MQTT_PORT     = 8883;             // MQTTS TLS/SSL
const char* MQTT_USER     = "ranova_esp32";
const char* MQTT_PASSWORD = "02082006";       // Password autentikasi EMQX
const char* MQTT_CLIENT_ID= "ranova-esp32-node1";

// Daftar Topik MQTT
const char* TOPIC_STATUS    = "ranova/device_001/status";
const char* TOPIC_TELEMETRY = "ranova/device_001/telemetry";
const char* TOPIC_SELECTION = "ranova/device_001/selection";
const char* TOPIC_COMMAND   = "ranova/device_001/command";

// ============================================================
// 2. PIN HARDWARE
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
// 3. PARAMETER OPERASI
// ============================================================
#define OVERHEAT_LIMIT_C     42.0     // Suhu kritis pemutus darurat (°C)
#define OVERHEAT_SAFE_C      37.0     // Suhu pemulihan histeresis (°C)
#define QRIS_TIMEOUT_MS      300000   // Timeout bayar: 5 menit (300.000 ms)

#define INTERVAL_TELEMETRY      3000  // Kirim telemetri PZEM & DHT22 tiap 3 detik
#define INTERVAL_BLINK          400   // Kecepatan kedip LED saat menunggu bayar (ms)
#define INTERVAL_WIFI_CHECK     30000 // Cek koneksi WiFi tiap 30 detik
#define INTERVAL_DHT_READ       1500  // Sampling DHT22 tiap 1.5 detik
#define DEBOUNCE_DELAY_MS       50    // Debounce tombol fisik software 50ms
#define SUCCESS_DISPLAY_MS      3500  // Durasi banner notifikasi sukses di LCD

// ============================================================
// 4. STRUKTUR DATA & OBJEK GLOBAL
// ============================================================
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT dht(PIN_DHT, DHTTYPE);
PZEM004Tv30 pzem(Serial2, PZEM_RX, PZEM_TX);

WiFiClientSecure mqttNetClient;
PubSubClient mqttClient(mqttNetClient);

const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";
WiFiClientSecure fbNetClient;

bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  http.begin(fbNetClient, String(FIREBASE_HOST) + "/" + path + ".json");
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(1500);
  int code = http.PUT(body);
  http.end();
  return (code >= 200 && code < 300);
}

enum SlotState { STANDBY, WAITING_PAYMENT, ACTIVE, PAUSED_OVERHEAT, EMERGENCY_STOP };

struct Slot {
  volatile SlotState state  = STANDBY;
  unsigned long waitStart   = 0;
  unsigned long activeStart = 0;
  unsigned long durationSec = 0; // 0 = Mode manual tanpa batas waktu (kontinu)
};

Slot slots[NUM_SLOTS];

// Variabel sinkronisasi tombol antar Core (Core 0 -> Core 1)
volatile bool btnTriggered[NUM_SLOTS] = {false, false, false};

// Variabel telemetri sensor (dibaca Core 1, ditampilkan Core 0)
volatile float sensorVoltage = 0.0;
volatile float sensorCurrent = 0.0;
volatile float sensorPower   = 0.0;
volatile float sensorEnergy  = 0.0;
volatile float sensorTemp    = 0.0;
volatile float sensorHumid   = 0.0;

// Variabel status sistem
volatile bool isOverheated           = false;
volatile int  successSlot            = -1;
volatile unsigned long tSuccess      = 0;

unsigned long lastMqttReconnect = 0;
unsigned long tTelemetry        = 0;
unsigned long tWifiCheck        = 0;
unsigned long tDhtRead          = 0;

TaskHandle_t uiTaskHandle = NULL;

// Deklarasi Fungsi
void connectWiFi();
void checkWiFi();
void updateTimers();
void updateTelemetry();
void handleOverheat();
void setSlotStandby(int idx);
void publishSlotState(int idx);
void publishSelection(int idx);
void publishSelectionClear();
void mqttCallback(char* topic, byte* payload, unsigned int length);

// ============================================================
// 5. CORE 0 TASK: USER INTERFACE (LCD, LED Ring & Debounce Tombol)
// ============================================================
// Berjalan mandiri pada Core 0 (Pro CPU) terpisah dari jaringan Core 1.
// Menjamin animasi LED berkedip sempurna & LCD stabil tanpa jitter.
void uiTask(void *pvParameters) {
  unsigned long lastBtnDebounceTime[NUM_SLOTS] = {0, 0, 0};
  bool lastBtnPhysical[NUM_SLOTS]              = {HIGH, HIGH, HIGH};
  bool btnDebouncedState[NUM_SLOTS]            = {HIGH, HIGH, HIGH};

  unsigned long tBlink = 0;
  bool blinkState = false;

  for (;;) {
    unsigned long now = millis();

    // 1. Debounce Tombol Fisik (50ms)
    for (int i = 0; i < NUM_SLOTS; i++) {
      bool raw = digitalRead(PIN_BTN[i]);

      if (raw != lastBtnPhysical[i]) {
        lastBtnDebounceTime[i] = now;
      }
      lastBtnPhysical[i] = raw;

      if ((now - lastBtnDebounceTime[i]) > DEBOUNCE_DELAY_MS) {
        if (raw != btnDebouncedState[i]) {
          btnDebouncedState[i] = raw;
          if (btnDebouncedState[i] == LOW) {
            // Sinyalkan penekanan tombol ke Core 1
            btnTriggered[i] = true;
          }
        }
      }
    }

    // 2. Animasi Kedip LED Ring saat WAITING_PAYMENT
    if (now - tBlink >= INTERVAL_BLINK) {
      tBlink = now;
      blinkState = !blinkState;
    }

    // 3. Kontrol LED Ring Fisik
    if (isOverheated) {
      for (int i = 0; i < NUM_SLOTS; i++) {
        digitalWrite(PIN_LED[i], LOW);
      }
    } else {
      for (int i = 0; i < NUM_SLOTS; i++) {
        switch (slots[i].state) {
          case STANDBY:
          case PAUSED_OVERHEAT:
          case EMERGENCY_STOP:
            digitalWrite(PIN_LED[i], LOW);
            break;
          case WAITING_PAYMENT:
            digitalWrite(PIN_LED[i], blinkState ? HIGH : LOW);
            break;
          case ACTIVE:
            digitalWrite(PIN_LED[i], HIGH);
            break;
        }
      }
    }

    // 4. Update Tampilan LCD 16x2
    char r1[17], r2[17];

    if (isOverheated) {
      snprintf(r1, 17, "! OVERHEAT >%dC!", (int)OVERHEAT_LIMIT_C);
      snprintf(r2, 17, "Suhu Box: %4.1f C", sensorTemp);
      lcd.setCursor(0, 0); lcd.print(r1);
      lcd.setCursor(0, 1); lcd.print(r2);
    }
    else if (successSlot >= 0) {
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
      } else {
        successSlot = -1;
      }
    }
    else {
      // Cek apakah ada slot yang sedang menunggu pembayaran QRIS
      int waitingSlot = -1;
      for (int i = 0; i < NUM_SLOTS; i++) {
        if (slots[i].state == WAITING_PAYMENT) {
          waitingSlot = i;
          break;
        }
      }

      if (waitingSlot >= 0) {
        unsigned long elapsed = (now - slots[waitingSlot].waitStart) / 1000;
        int remaining = max(0, (int)(QRIS_TIMEOUT_MS / 1000) - (int)elapsed);
        snprintf(r1, 17, "Scan QRIS Slot %d", waitingSlot + 1);
        snprintf(r2, 17, "Tunggu: %3ds    ", remaining);
        lcd.setCursor(0, 0); lcd.print(r1);
        lcd.setCursor(0, 1); lcd.print(r2);
      } else {
        // Cek berapa slot aktif
        int activeCount = 0;
        for (int i = 0; i < NUM_SLOTS; i++) {
          if (slots[i].state == ACTIVE) activeCount++;
        }

        if (activeCount == 0) {
          snprintf(r1, 17, "  SMART  PLUG   ");
          snprintf(r2, 17, " Tekan Tombol...");
          lcd.setCursor(0, 0); lcd.print(r1);
          lcd.setCursor(0, 1); lcd.print(r2);
        } else {
          snprintf(r1, 17, "V: %3.0fV  I:%4.2fA", sensorVoltage, sensorCurrent);
          snprintf(r2, 17, "Daya: %5.1f Watt", sensorPower);
          lcd.setCursor(0, 0); lcd.print(r1);
          lcd.setCursor(0, 1); lcd.print(r2);
        }
      }
    }

    vTaskDelay(pdMS_TO_TICKS(20)); // Eksekusi dengan refresh rate 50Hz (setiap 20ms)
  }
}

// ============================================================
// 6. SETUP UTAMA (CORE 1)
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n==============================================");
  Serial.println("  RANOVA Smart Plug 3-Slot — Full-MQTT v3.0   ");
  Serial.println("  (FreeRTOS Dual-Core + EMQX Cloud MQTTS)    ");
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

  // Inisialisasi LCD 16x2
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Memulai...    ");

  // Inisialisasi Sensor DHT22 & PZEM-004T
  dht.begin();
  Serial2.begin(9600, SERIAL_8N1, PZEM_RX, PZEM_TX);
  Serial.println("[INIT] DHT22 GPIO4 | PZEM Serial2 RX16/TX17");

  // Konfigurasi TLS Client untuk MQTT & Firebase
  mqttNetClient.setInsecure();
  fbNetClient.setInsecure();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512); // Buffer lega untuk JSON telemetri & command

  // Sambungkan ke WiFi
  connectWiFi();

  // Reset seluruh slot ke STANDBY
  for (int i = 0; i < NUM_SLOTS; i++) {
    slots[i].state = STANDBY;
    slots[i].durationSec = 0;
    slots[i].waitStart = 0;
    slots[i].activeStart = 0;
    digitalWrite(PIN_RELAY[i], HIGH);
    digitalWrite(PIN_LED[i], LOW);
  }

  // Luncurkan Core 0 UI Task (LCD, LED, Debounce Tombol)
  xTaskCreatePinnedToCore(
    uiTask,          // Fungsi task
    "uiTask",        // Nama task
    4096,            // Ukuran stack (4 KB)
    NULL,            // Parameter
    1,               // Prioritas (1 = Standar)
    &uiTaskHandle,   // Task handle
    0                // Core 0 (Pro CPU)
  );
  Serial.println("[FreeRTOS] UI Task berjalan pada Core 0.");

  Serial.println("\n[SIAP] Sistem dalam mode STANDBY (Full-MQTT).");
  Serial.println(">> Tekan salah satu dari 3 tombol untuk memulai sewa! <<\n");
}

// ============================================================
// 7. LOOP UTAMA (CORE 1: MQTT, State Machine, Timer, Sensor)
// ============================================================
void loop() {
  unsigned long now = millis();

  // 1. Tangani Event Penekanan Tombol Fisik dari Core 0
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (btnTriggered[i]) {
      btnTriggered[i] = false; // Reset trigger

      if (!isOverheated) {
        if (slots[i].state == STANDBY) {
          Serial.printf("\n[BTN %d] Ditekan -> WAITING_PAYMENT\n", i + 1);
          slots[i].state = WAITING_PAYMENT;
          slots[i].waitStart = now;

          publishSlotState(i);
          publishSelection(i);
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

  // 2. Proteksi Overheat Termal Histeresis (Prioritas Keamanan Tertinggi)
  handleOverheat();

  // 3. Update Timer & Hitung Mundur Sewa
  updateTimers();

  // 4. Update Fisik Relay (Core 1)
  if (isOverheated) {
    for (int i = 0; i < NUM_SLOTS; i++) {
      digitalWrite(PIN_RELAY[i], HIGH);
    }
  } else {
    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == ACTIVE) {
        digitalWrite(PIN_RELAY[i], LOW); // Relay ON
      } else {
        digitalWrite(PIN_RELAY[i], HIGH); // Relay OFF
      }
    }
  }

  // 5. Upload Telemetri Sensor Realtime via MQTT
  if (now - tTelemetry >= INTERVAL_TELEMETRY) {
    tTelemetry = now;
    updateTelemetry();
  }

  // 6. Cek Rutin Koneksi WiFi
  if (now - tWifiCheck >= INTERVAL_WIFI_CHECK) {
    tWifiCheck = now;
    checkWiFi();
  }

  // 7. Manajemen Koneksi MQTT (Non-Blocking dengan LWT)
  if (WiFi.status() == WL_CONNECTED) {
    if (!mqttClient.connected()) {
      if (now - lastMqttReconnect >= 5000) {
        lastMqttReconnect = now;
        Serial.print("[MQTT] Menghubungkan ke EMQX Cloud...");

        // Connect dengan Last Will and Testament (LWT)
        // Jika steker dicabut / koneksi putus tiba-tiba, broker EMQX otomatis mem-publish status offline
        if (mqttClient.connect(MQTT_CLIENT_ID, MQTT_USER, MQTT_PASSWORD,
                               TOPIC_STATUS, 1, true, "{\"state\":\"offline\"}")) {
          Serial.println(" OK! Terhubung.");

          // Berlangganan topik perintah kontrol (Downlink)
          mqttClient.subscribe(TOPIC_COMMAND);

          // Publish status online (Retained = true)
          mqttClient.publish(TOPIC_STATUS, "{\"state\":\"online\"}", true);

          // Broadcast seluruh status slot awal
          for (int i = 0; i < NUM_SLOTS; i++) {
            publishSlotState(i);
          }
          publishSelectionClear();
        } else {
          Serial.printf(" Gagal (rc=%d)\n", mqttClient.state());
        }
      }
    } else {
      mqttClient.loop();
    }
  }
}

// ============================================================
// 8. KONEKSI & REKONEKSI WIFI
// ============================================================
void connectWiFi() {
  Serial.print("\n[WiFi] Menghubungkan ke ");
  Serial.println(WIFI_SSID);

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
  }
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
// 9. UPDATE TIMERS & HITUNG MUNDUR SEWA
// ============================================================
void updateTimers() {
  if (isOverheated) return;

  unsigned long now = millis();

  for (int i = 0; i < NUM_SLOTS; i++) {
    // Timeout 5 menit saat menunggu pembayaran QRIS
    if (slots[i].state == WAITING_PAYMENT) {
      if (now - slots[i].waitStart >= QRIS_TIMEOUT_MS) {
        Serial.printf("[TIMEOUT] Slot %d: 5 menit tanpa bayar. Reset.\n", i + 1);
        setSlotStandby(i);
      }
    }

    // Countdown sewa: HANYA jika durationSec > 0
    if (slots[i].state == ACTIVE) {
      if (slots[i].durationSec > 0) {
        unsigned long elapsed = (now - slots[i].activeStart) / 1000;
        if (elapsed >= slots[i].durationSec) {
          Serial.printf("[SELESAI] Slot %d: Waktu sewa habis!\n", i + 1);
          setSlotStandby(i);
        }
      }
      // Jika durationSec == 0: Mode Bypass Kontinu (Relay ON tanpa batas waktu sampai dimatikan manual)
    }
  }
}

// ============================================================
// 10. BACA SENSOR & UPLOAD TELEMETRI MQTT (NON-BLOCKING)
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
    Serial.println("[PZEM] Menunggu respons (NAN). Cek steker 220V & kabel.");
  }

  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (!isnan(t)) sensorTemp  = t;
  if (!isnan(h)) sensorHumid = h;

  if (mqttClient.connected()) {
    StaticJsonDocument<256> doc;
    doc["voltage"]     = serialized(String(sensorVoltage, 1));
    doc["current"]     = serialized(String(sensorCurrent, 2));
    doc["power"]       = serialized(String(sensorPower, 1));
    doc["energy"]      = serialized(String(sensorEnergy, 4));
    doc["temperature"] = serialized(String(sensorTemp, 1));
    doc["humidity"]    = serialized(String(sensorHumid, 1));
    doc["updated_at"]  = (unsigned long)time(nullptr);

    char buf[256];
    serializeJson(doc, buf);
    mqttClient.publish(TOPIC_TELEMETRY, buf);

    // Sync berkala ke Firebase RTDB (tiap ~6 detik) untuk histori database Laravel & SSR
    static unsigned long lastFbTelemetry = 0;
    if (millis() - lastFbTelemetry >= 6000) {
      lastFbTelemetry = millis();
      fbPut("sensors", buf);
    }
  }
}

// ============================================================
// 11. PROTEKSI OVERHEAT (>=42°C) DENGAN PENDINGINAN (<37°C)
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
    Serial.println("  THERMAL PAUSE: Mematikan seluruh relay seketika!");
    Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n");

    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == ACTIVE) {
        if (slots[i].durationSec > 0) {
          unsigned long elapsed = (now - slots[i].activeStart) / 1000;
          if (elapsed < slots[i].durationSec) {
            slots[i].durationSec -= elapsed; // Sisa durasi dibekukan presisi
          } else {
            slots[i].durationSec = 0;
          }
        }
        // Ubah state secara eksplisit ke PAUSED_OVERHEAT
        slots[i].state = PAUSED_OVERHEAT;
        publishSlotState(i);
      }
      else if (slots[i].state == WAITING_PAYMENT) {
        setSlotStandby(i);
      }
      digitalWrite(PIN_RELAY[i], HIGH);
    }
  }
  // Pemulihan Otomatis: Suhu turun ke < OVERHEAT_SAFE_C (37.0°C)
  else if (isOverheated && sensorTemp > 0 && sensorTemp < OVERHEAT_SAFE_C) {
    isOverheated = false;

    Serial.println("\n============================================");
    Serial.printf("  SUHU AMAN (%.1f°C < %.1f°C) — AUTO RECOVERY RESUME!\n", sensorTemp, OVERHEAT_SAFE_C);
    Serial.println("============================================");

    for (int i = 0; i < NUM_SLOTS; i++) {
      if (slots[i].state == PAUSED_OVERHEAT) {
        slots[i].state = ACTIVE;
        slots[i].activeStart = millis();

        digitalWrite(PIN_RELAY[i], LOW);
        Serial.printf("[THERMAL RESUME] Slot %d aktif kembali! Sisa: %lu dtk.\n", i + 1, slots[i].durationSec);
        publishSlotState(i);
      } else {
        setSlotStandby(i);
      }
    }
  }
}

// ============================================================
// 12. RESET SLOT KE STANDBY
// ============================================================
void setSlotStandby(int idx) {
  slots[idx].state = STANDBY;
  slots[idx].durationSec = 0;
  slots[idx].waitStart = 0;
  slots[idx].activeStart = 0;

  digitalWrite(PIN_RELAY[idx], HIGH);
  digitalWrite(PIN_LED[idx], LOW);

  publishSlotState(idx);
  publishSelectionClear();

  // Sync status slot STANDBY ke Firebase RTDB
  String slotKey = "slot" + String(idx + 1);
  String body = "{\"status\":\"STANDBY\",\"duration_seconds\":0,\"active_duration\":0,\"amount_paid\":0,\"nominal_paid\":0,\"started_at\":0,\"activated_at\":0,\"expires_at\":0}";
  fbPut(slotKey, body);
  fbPut("slots/" + slotKey, body);

  Serial.printf("[SLOT %d] Reset -> STANDBY terkirim.\n", idx + 1);
}

// ============================================================
// 13. BROADCAST STATUS SLOT KE MQTT (Retained = true)
// ============================================================
void publishSlotState(int idx) {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<256> doc;
  doc["slot"] = idx + 1;

  const char* stStr = "STANDBY";
  if (slots[idx].state == ACTIVE)               stStr = "ACTIVE";
  else if (slots[idx].state == WAITING_PAYMENT) stStr = "WAITING_PAYMENT";
  else if (slots[idx].state == PAUSED_OVERHEAT) stStr = "PAUSED_OVERHEAT";
  else if (slots[idx].state == EMERGENCY_STOP)  stStr = "EMERGENCY_STOP";

  doc["status"]       = stStr;
  doc["duration_sec"] = slots[idx].durationSec;

  if (slots[idx].state == ACTIVE && slots[idx].durationSec > 0) {
    unsigned long elapsed = (millis() - slots[idx].activeStart) / 1000;
    doc["remaining_sec"] = (elapsed < slots[idx].durationSec) ? (slots[idx].durationSec - elapsed) : 0;
  } else if (slots[idx].state == PAUSED_OVERHEAT) {
    doc["remaining_sec"] = slots[idx].durationSec; // Nilai sisa durasi yang dibekukan
  } else {
    doc["remaining_sec"] = 0;
  }

  doc["mode"] = (slots[idx].durationSec == 0 && slots[idx].state == ACTIVE) ? "MANUAL_OVERRIDE" : "RENTAL";

  char buf[256];
  serializeJson(doc, buf);
  String top = "ranova/device_001/slots/" + String(idx + 1) + "/state";
  mqttClient.publish(top.c_str(), buf, true); // Retained true agar subscriber baru langsung tahu status slot
}

void publishSelection(int idx) {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<128> doc;
  doc["slot"]      = "slot" + String(idx + 1);
  doc["slot_num"]  = idx + 1;
  doc["status"]    = "WAITING_PAYMENT";
  doc["timestamp"] = (unsigned long)time(nullptr);

  char buf[128];
  serializeJson(doc, buf);
  mqttClient.publish(TOPIC_SELECTION, buf, true);

  // Sync ke Firebase RTDB untuk Mayar Webhook & Dashboard SSR
  String slotKey = "slot" + String(idx + 1);
  fbPut("system/active_selection", buf);
  fbPut(slotKey + "/status", "\"WAITING_PAYMENT\"");
  fbPut("slots/" + slotKey + "/status", "\"WAITING_PAYMENT\"");
}

void publishSelectionClear() {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<128> doc;
  doc["slot"]      = "none";
  doc["slot_num"]  = 0;
  doc["status"]    = "IDLE";
  doc["timestamp"] = (unsigned long)time(nullptr);

  char buf[128];
  serializeJson(doc, buf);
  mqttClient.publish(TOPIC_SELECTION, buf, true);

  // Sync ke Firebase RTDB
  fbPut("system/active_selection", buf);
}

// ============================================================
// 14. HANDLER PERINTAH MQTT MASUK (Downlink Control)
// ============================================================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg = "";
  for (unsigned int i = 0; i < length; i++) msg += (char)payload[i];
  Serial.printf("\n[MQTT RX] Topik: %s | Payload: %s\n", topic, msg.c_str());

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, msg);
  String action = "";
  int targetSlot = 0;
  unsigned long duration = 0;

  if (!err) {
    action     = doc["action"] | "";
    targetSlot = doc["slot"] | 0;
    duration   = doc["duration"] | 0;
  } else {
    // Fallback format perintah teks
    if (msg.startsWith("START_")) {
      action = "START";
      targetSlot = msg.substring(6).toInt();
    } else if (msg.startsWith("STOP_")) {
      action = "STOP";
      targetSlot = msg.substring(5).toInt();
    } else if (msg == "EMERGENCY") {
      action = "EMERGENCY";
    }
  }

  // 1. Perintah START (Aktivasi Sewa atau Bypass Manual)
  if (action == "START" && targetSlot >= 1 && targetSlot <= NUM_SLOTS) {
    int idx = targetSlot - 1;
    if (!isOverheated) {
      slots[idx].state       = ACTIVE;
      slots[idx].durationSec = duration; // 0 = Kontinu (Manual), >0 = Sewa bertarif
      slots[idx].activeStart = millis();

      digitalWrite(PIN_RELAY[idx], LOW);
      digitalWrite(PIN_LED[idx], HIGH);

      successSlot = idx;
      tSuccess = millis();

      publishSlotState(idx);
      publishSelectionClear();

      // Sync status ACTIVE ke Firebase RTDB
      String slotKey = "slot" + String(targetSlot);
      unsigned long nowSec = (unsigned long)time(nullptr);
      String body = "{\"status\":\"ACTIVE\",\"active_duration\":" + String(duration) + ",\"duration_seconds\":" + String(duration) + ",\"started_at\":" + String(nowSec) + ",\"activated_at\":" + String(nowSec) + "}";
      fbPut(slotKey, body);
      fbPut("slots/" + slotKey, body);

      Serial.println("\n=================================================");
      if (slots[idx].durationSec == 0) {
        Serial.printf("  [MQTT CMD] ADMIN BYPASS AKTIF! RELAY %d ON (KONTINU)!\n", targetSlot);
      } else {
        Serial.printf("  [MQTT CMD] PEMBAYARAN SUKSES! RELAY %d ON!\n", targetSlot);
        Serial.printf("  Durasi: %lu detik (%lu menit)\n", slots[idx].durationSec, slots[idx].durationSec / 60);
      }
      Serial.println("=================================================");
    } else {
      Serial.println("[MQTT CMD] Ditolak: Sistem sedang dalam proteksi OVERHEAT!");
    }
  }
  // 2. Perintah STOP (Mematikan Slot Tertentu)
  else if (action == "STOP" && targetSlot >= 1 && targetSlot <= NUM_SLOTS) {
    int idx = targetSlot - 1;
    setSlotStandby(idx);
    Serial.printf("[MQTT CMD] Slot %d BERHASIL DIMATIKAN!\n", targetSlot);
  }
  // 3. Perintah EMERGENCY CUTOFF
  else if (action == "EMERGENCY") {
    Serial.println("[MQTT CMD] !!! EMERGENCY STOP DIAKTIFKAN !!!");
    for (int i = 0; i < NUM_SLOTS; i++) {
      setSlotStandby(i);
    }
  }
}
