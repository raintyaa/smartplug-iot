#include <WiFi.h>
#include <time.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <PZEM004Tv30.h>

const char* WIFI_SSID     = "RedmiNote";
const char* WIFI_PASSWORD = "12345678";
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

#define NUM_SLOTS 3

const int PIN_BTN[NUM_SLOTS]   = {27, 14, 12};
const int PIN_LED[NUM_SLOTS]   = {32, 33, 25};
const int PIN_RELAY[NUM_SLOTS] = {23, 19, 18};
const int PIN_RELAY_SPARE      = 26;

#define PIN_DHT    4
#define DHTTYPE    DHT22
#define PZEM_RX    16
#define PZEM_TX    17
#define LCD_ADDR   0x27
#define LCD_COLS   16
#define LCD_ROWS   2

#define OVERHEAT_LIMIT_C     50.0
#define OVERHEAT_SAFE_C      40.0
#define QRIS_TIMEOUT_MS      300000

#define INTERVAL_FIREBASE_POLL  2000
#define INTERVAL_TELEMETRY      5000
#define INTERVAL_LCD_REFRESH    500
#define INTERVAL_BLINK          400
#define INTERVAL_WIFI_CHECK     30000
#define INTERVAL_LCD_ROTATE     3000
#define INTERVAL_DHT_READ       1500

LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT dht(PIN_DHT, DHTTYPE);
PZEM004Tv30 pzem(Serial2, PZEM_RX, PZEM_TX);
WiFiClientSecure secureClient;

enum SlotState { STANDBY, WAITING_PAYMENT, ACTIVE, EMERGENCY_STOP };

struct Slot {
  volatile SlotState state  = STANDBY;
  unsigned long waitStart   = 0;
  unsigned long activeStart = 0;
  unsigned long durationSec = 0;
};
Slot slots[NUM_SLOTS];

volatile bool btnTriggered[NUM_SLOTS] = {false, false, false};
volatile unsigned long lastBtnTime[NUM_SLOTS] = {0, 0, 0};
bool lastBtnPhysical[NUM_SLOTS] = {HIGH, HIGH, HIGH};

void IRAM_ATTR isrBtn1() {
  unsigned long now = millis();
  if (now - lastBtnTime[0] > 200) {
    lastBtnTime[0] = now;
    btnTriggered[0] = true;
    if (slots[0].state == ACTIVE) {
      digitalWrite(PIN_RELAY[0], HIGH);
      digitalWrite(PIN_LED[0], LOW);
    }
  }
}

void IRAM_ATTR isrBtn2() {
  unsigned long now = millis();
  if (now - lastBtnTime[1] > 200) {
    lastBtnTime[1] = now;
    btnTriggered[1] = true;
    if (slots[1].state == ACTIVE) {
      digitalWrite(PIN_RELAY[1], HIGH);
      digitalWrite(PIN_LED[1], LOW);
    }
  }
}

void IRAM_ATTR isrBtn3() {
  unsigned long now = millis();
  if (now - lastBtnTime[2] > 200) {
    lastBtnTime[2] = now;
    btnTriggered[2] = true;
    if (slots[2].state == ACTIVE) {
      digitalWrite(PIN_RELAY[2], HIGH);
      digitalWrite(PIN_LED[2], LOW);
    }
  }
}

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
unsigned long tLcdRotate    = 0;
unsigned long tDhtRead      = 0;
bool blinkState             = false;
bool isOverheated           = false;
int  lcdRotateIdx           = 0;

int      successSlot = -1;
unsigned long tSuccess = 0;
#define SUCCESS_DISPLAY_MS  4000

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

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n==============================================");
  Serial.println("  SMART PLUG 3-Slot — Firmware Final v1.0");
  Serial.println("==============================================");

  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_RELAY[i], OUTPUT);
    digitalWrite(PIN_RELAY[i], HIGH);
  }
  pinMode(PIN_RELAY_SPARE, OUTPUT);
  digitalWrite(PIN_RELAY_SPARE, HIGH);

  for (int i = 0; i < NUM_SLOTS; i++) {
    pinMode(PIN_BTN[i], INPUT_PULLUP);
    pinMode(PIN_LED[i], OUTPUT);
    digitalWrite(PIN_LED[i], LOW);
  }
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[0]), isrBtn1, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[1]), isrBtn2, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_BTN[2]), isrBtn3, FALLING);

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Memulai...    ");

  dht.begin();
  Serial2.begin(9600, SERIAL_8N1, PZEM_RX, PZEM_TX);
  Serial.println("[INIT] DHT22 GPIO4 | PZEM Serial2 RX16/TX17");

  secureClient.setInsecure();

  connectWiFi();

  for (int i = 0; i < NUM_SLOTS; i++) {
    setSlotStandby(i);
  }

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  SMART  PLUG   ");
  lcd.setCursor(0, 1); lcd.print(" Tekan Tombol...");
  Serial.println("\n[SIAP] Sistem dalam mode STANDBY.");
  Serial.println(">> Tekan salah satu dari 3 tombol untuk memulai sewa! <<\n");
}

void loop() {
  unsigned long now = millis();

  handleOverheat();

  checkButtons();

  if (now - tBlink >= INTERVAL_BLINK) {
    tBlink = now;
    blinkState = !blinkState;
  }

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

  updateTimers();

  updateHardware();

  if (now - tLcdRefresh >= INTERVAL_LCD_REFRESH) {
    tLcdRefresh = now;
    updateLcdDisplay();
  }

  if (now - tTelemetry >= INTERVAL_TELEMETRY) {
    tTelemetry = now;
    updateTelemetry();
  }

  if (now - tWifiCheck >= INTERVAL_WIFI_CHECK) {
    tWifiCheck = now;
    checkWiFi();
  }
}

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

void checkButtons() {
  if (isOverheated) return;

  for (int i = 0; i < NUM_SLOTS; i++) {
    bool raw = digitalRead(PIN_BTN[i]);
    bool pressEvent = false;

    if (btnTriggered[i]) {
      btnTriggered[i] = false;
      pressEvent = true;
    }

    else if (lastBtnPhysical[i] == HIGH && raw == LOW) {
      delay(20);
      if (digitalRead(PIN_BTN[i]) == LOW) {
        pressEvent = true;
      }
    }
    lastBtnPhysical[i] = raw;

    if (!pressEvent) continue;

    if (slots[i].state == STANDBY) {

      Serial.printf("\n[BTN %d] Ditekan -> WAITING_PAYMENT\n", i + 1);
      slots[i].state = WAITING_PAYMENT;
      slots[i].waitStart = millis();

      updateHardware();
      updateLcdDisplay();

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

        uint32_t durSec = sObj["duration_seconds"] | sObj["active_duration"] | 0;

        if (strcmp(status, "ACTIVE") == 0 && slots[i].state != ACTIVE) {
          slots[i].state = ACTIVE;
          slots[i].durationSec = durSec > 0 ? durSec : 900;
          slots[i].activeStart = millis();

          digitalWrite(PIN_RELAY[i], LOW);
          digitalWrite(PIN_LED[i], HIGH);

          successSlot = i;
          tSuccess = millis();

          Serial.println("\n=================================================");
          Serial.printf("  PEMBAYARAN DITERIMA! RELAY %d AKTIF!\n", i + 1);
          Serial.printf("  Durasi: %lu detik (%lu menit)\n", slots[i].durationSec, slots[i].durationSec / 60);
          Serial.println("=================================================");
        }

        if (strcmp(status, "STANDBY") == 0 && slots[i].state == ACTIVE) {
          Serial.printf("[REMOTE] Dashboard mematikan Slot %d.\n", i + 1);
          setSlotStandby(i);
        }
      }
    }
  }

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

void updateTimers() {
  for (int i = 0; i < NUM_SLOTS; i++) {

    if (slots[i].state == WAITING_PAYMENT) {
      if (millis() - slots[i].waitStart >= QRIS_TIMEOUT_MS) {
        Serial.printf("[TIMEOUT] Slot %d: 5 menit tanpa bayar. Reset.\n", i + 1);
        setSlotStandby(i);
      }
    }

    if (slots[i].state == ACTIVE) {
      unsigned long elapsed = (millis() - slots[i].activeStart) / 1000;
      if (elapsed >= slots[i].durationSec) {
        Serial.printf("[SELESAI] Slot %d: Waktu sewa habis!\n", i + 1);
        setSlotStandby(i);
      }
    }
  }
}

void updateHardware() {
  if (isOverheated) {

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
        digitalWrite(PIN_RELAY[i], LOW);
        digitalWrite(PIN_LED[i], HIGH);
        break;
      case WAITING_PAYMENT:
        digitalWrite(PIN_RELAY[i], HIGH);
        digitalWrite(PIN_LED[i], blinkState ? HIGH : LOW);
        break;
      default:
        digitalWrite(PIN_RELAY[i], HIGH);
        digitalWrite(PIN_LED[i], LOW);
        break;
    }
  }

  digitalWrite(PIN_RELAY_SPARE, HIGH);
}

void fmtTime(unsigned long totalSec, char* buf, size_t len) {
  unsigned long m = totalSec / 60;
  unsigned long s = totalSec % 60;
  snprintf(buf, len, "%02lu:%02lu", m, s);
}

void updateLcdDisplay() {
  char r1[17], r2[17];
  unsigned long now = millis();

  if (isOverheated) {
    snprintf(r1, 17, "! OVERHEAT >50C!");
    snprintf(r2, 17, "Suhu Box: %4.1f C", sensorTemp);
    lcd.setCursor(0, 0); lcd.print(r1);
    lcd.setCursor(0, 1); lcd.print(r2);
    return;
  }

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

  int activeCount = 0;
  for (int i = 0; i < NUM_SLOTS; i++) {
    if (slots[i].state == ACTIVE) {
      activeCount++;
    }
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

    bool ok = fbPut("sensors", json);
    if (ok) {
      Serial.println("[FIREBASE] Telemetri sensor berhasil di-update!");
    } else {
      Serial.println("[FIREBASE] GAGAL upload telemetri! Pastikan Rules Firebase aktif.");
    }
  }
}

void handleOverheat() {
  unsigned long now = millis();

  if (now - tDhtRead >= INTERVAL_DHT_READ) {
    tDhtRead = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t)) sensorTemp  = t;
    if (!isnan(h)) sensorHumid = h;
  }

  if (!isOverheated && sensorTemp >= OVERHEAT_LIMIT_C) {
    isOverheated = true;

    Serial.println("\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
    Serial.printf("  DARURAT: SUHU %.1f°C >= 50°C — EMERGENCY STOP!\n", sensorTemp);
    Serial.println("  Seluruh relay & LED dimatikan! Tombol dikunci.");
    Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");

    for (int i = 0; i < NUM_SLOTS; i++) {
      slots[i].state = EMERGENCY_STOP;
      digitalWrite(PIN_RELAY[i], HIGH);
      digitalWrite(PIN_LED[i], LOW);
    }
    digitalWrite(PIN_RELAY_SPARE, HIGH);

    fbPut("system/emergency_alert", "true");
    for (int i = 0; i < NUM_SLOTS; i++) {
      String slotKey = "slot" + String(i + 1);
      fbPut("slots/" + slotKey + "/status", "\"EMERGENCY_STOP\"");
      fbPut(slotKey + "/status", "\"EMERGENCY_STOP\"");
    }
  }

  else if (isOverheated && sensorTemp > 0 && sensorTemp < OVERHEAT_SAFE_C) {
    isOverheated = false;

    Serial.println("\n============================================");
    Serial.printf("  SUHU SUDAH AMAN (%.1f°C < 40°C) — AUTO RECOVERY!\n", sensorTemp);
    Serial.println("  Sistem kembali normal ke mode STANDBY.");
    Serial.println("============================================");

    fbPut("system/emergency_alert", "false");

    for (int i = 0; i < NUM_SLOTS; i++) {
      setSlotStandby(i);
    }
  }
}

void setSlotStandby(int idx) {
  slots[idx].state = STANDBY;
  slots[idx].durationSec = 0;

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
