/*
 * ============================================================
 * RANOVA Smart Plug - Firmware ESP32
 * Versi: 1.0 (Wokwi Simulator)
 * ============================================================
 * Alur Sistem:
 *  1. Tekan Tombol Slot -> ESP32 tulis WAITING_PAYMENT ke Firebase
 *  2. Pengguna scan QRIS Mayar -> Bayar -> Webhook Vercel -> Firebase ACTIVE
 *  3. ESP32 deteksi perubahan Firebase -> Relay ON + LED solid + LCD countdown
 *  4. Countdown habis -> Relay OFF -> Firebase kembali STANDBY
 *
 * Pin GPIO:
 *  - Tombol  : GPIO 27, 14, 12 (INPUT_PULLUP, LOW = ditekan)
 *  - LED Ring: GPIO 32, 33, 25 (HIGH = ON)
 *  - Relay   : GPIO 23, 19, 18 (Active-LOW di hardware; HIGH=ON di Wokwi)
 *  - LCD I2C : SDA=GPIO21, SCL=GPIO22, Alamat=0x27
 *  - DHT22   : GPIO 4
 *
 * Libraries:
 *  - ArduinoJson (Benoit Blanchon)
 *  - LiquidCrystal I2C (Frank de Brabander)
 *  - DHT sensor library (Adafruit)
 *  - Adafruit Unified Sensor (Adafruit)
 * ============================================================
 */

// Uncomment baris berikut untuk mode hardware FISIK (relay Active-LOW)
#define WOKWI_SIM

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>

// --- KONFIGURASI ---
const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASS     = "";
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// --- PIN ---
const int BTN[3]      = {27, 14, 12};
const int LED_RING[3] = {32, 33, 25};
const int RELAY[3]    = {23, 19, 18};
#define DHT_PIN  4
#define DHT_TYPE DHT22

// --- OBJEK ---
LiquidCrystal_I2C lcd(0x27, 16, 2);
DHT               dht(DHT_PIN, DHT_TYPE);
WiFiClientSecure  secureClient;

// --- STATE SLOT ---
struct SlotState {
  String   fbStatus = "STANDBY";
  uint32_t durSec   = 0;
  uint32_t cntDown  = 0;
  uint32_t startMs  = 0;
  bool     tracking = false;
};
SlotState slots[3];

int      selSlot    = -1;
bool     prevBtn[3] = {true, true, true};
uint32_t tPoll      = 0, tSensor = 0, tLCD = 0, tBlink = 0, tWait = 0;
bool     blinkOn    = false;

const uint32_t I_POLL   = 2000;
const uint32_t I_SENSOR = 15000;
const uint32_t I_LCD    = 500;
const uint32_t I_BLINK  = 400;
const uint32_t I_WAIT   = 300000;

// --- Kontrol Relay (bedakan Wokwi vs hardware) ---
void setRelay(int idx, bool on) {
#ifdef WOKWI_SIM
  digitalWrite(RELAY[idx], on ? HIGH : LOW);
#else
  digitalWrite(RELAY[idx], on ? LOW : HIGH);  // Active-LOW
#endif
}

// --- Firebase GET ---
String fbGet(const String& path) {
  if (WiFi.status() != WL_CONNECTED) return "null";
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + path + ".json");
  http.setTimeout(6000);
  int code = http.GET();
  String res = "null";
  if (code == HTTP_CODE_OK) res = http.getString();
  else Serial.printf("[FB GET %d] %s\n", code, path.c_str());
  http.end();
  return res;
}

// --- Firebase PUT ---
bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + path + ".json");
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(6000);
  int code = http.PUT(body);
  http.end();
  return (code == HTTP_CODE_OK);
}

// --- Tulis seleksi tombol ke Firebase ---
void writeSelection(int idx) {
  StaticJsonDocument<128> doc;
  doc["slot"]      = "slot" + String(idx + 1);
  doc["status"]    = "WAITING_PAYMENT";
  doc["timestamp"] = 0;
  String body;
  serializeJson(doc, body);
  if (fbPut("/system/active_selection", body))
    Serial.printf("[BTN] Slot %d -> Firebase: WAITING_PAYMENT\n", idx + 1);
}

// --- Reset slot ke STANDBY di Firebase ---
void resetSlotFirebase(int idx) {
  String path = "/slots/slot" + String(idx + 1);
  StaticJsonDocument<128> slotDoc;
  slotDoc["status"]           = "STANDBY";
  slotDoc["duration_seconds"] = 0;
  slotDoc["amount_paid"]      = 0;
  slotDoc["activated_at"]     = 0;
  slotDoc["expires_at"]       = 0;
  String slotBody;
  serializeJson(slotDoc, slotBody);
  fbPut(path, slotBody);

  StaticJsonDocument<64> sel;
  sel["slot"]      = "none";
  sel["status"]    = "IDLE";
  sel["timestamp"] = 0;
  String selBody;
  serializeJson(sel, selBody);
  fbPut("/system/active_selection", selBody);
  Serial.printf("[SLOT %d] Reset STANDBY.\n", idx + 1);
}

// --- Poll semua slot dari Firebase ---
void pollFirebase() {
  String raw = fbGet("/slots");
  if (raw.length() < 5) return;

  DynamicJsonDocument doc(1024);
  if (deserializeJson(doc, raw)) return;

  for (int i = 0; i < 3; i++) {
    String key = "slot" + String(i + 1);
    if (!doc.containsKey(key)) continue;

    JsonObject s     = doc[key];
    String newStatus = s["status"] | "STANDBY";
    uint32_t newDur  = s["duration_seconds"] | 0;

    // Transisi -> ACTIVE
    if (newStatus == "ACTIVE" && !slots[i].tracking) {
      slots[i].durSec   = newDur;
      slots[i].cntDown  = newDur;
      slots[i].startMs  = millis();
      slots[i].tracking = true;
      if (selSlot == i) selSlot = -1;
      Serial.printf("[SLOT %d] AKTIF! %.1f menit\n", i+1, newDur/60.0);
    }

    // Transisi ACTIVE -> lain (dari luar)
    if (slots[i].tracking && newStatus != "ACTIVE") {
      slots[i].tracking = false;
      slots[i].cntDown  = 0;
      setRelay(i, false);
      digitalWrite(LED_RING[i], LOW);
      Serial.printf("[SLOT %d] Dihentikan.\n", i+1);
    }

    slots[i].fbStatus = newStatus;
    slots[i].durSec   = newDur;
  }
}

// --- Update countdown ---
void updateTimers() {
  uint32_t now = millis();
  for (int i = 0; i < 3; i++) {
    if (!slots[i].tracking) continue;
    uint32_t elapsed = (now - slots[i].startMs) / 1000;
    if (elapsed >= slots[i].durSec) {
      slots[i].cntDown  = 0;
      slots[i].tracking = false;
      setRelay(i, false);
      digitalWrite(LED_RING[i], LOW);
      Serial.printf("[SLOT %d] Waktu habis!\n", i+1);
      resetSlotFirebase(i);
    } else {
      slots[i].cntDown = slots[i].durSec - elapsed;
    }
  }
}

// --- Update relay & LED ---
void updateHardware() {
  uint32_t now = millis();
  if (now - tBlink >= I_BLINK) { tBlink = now; blinkOn = !blinkOn; }

  for (int i = 0; i < 3; i++) {
    if (slots[i].tracking && slots[i].cntDown > 0) {
      setRelay(i, true);
      digitalWrite(LED_RING[i], HIGH);
    } else if (selSlot == i) {
      setRelay(i, false);
      digitalWrite(LED_RING[i], blinkOn ? HIGH : LOW);
    } else {
      setRelay(i, false);
      digitalWrite(LED_RING[i], LOW);
    }
  }
}

// --- Update LCD ---
void updateLCD() {
  int show = -1;
  for (int i = 0; i < 3; i++)
    if (slots[i].tracking && slots[i].cntDown > 0) { show = i; break; }
  if (show == -1 && selSlot != -1) show = selSlot;

  char r1[17], r2[17];
  if (show == -1) {
    snprintf(r1, 17, "  RANOVA PLUG   ");
    snprintf(r2, 17, " Tekan Tombol...");
  } else if (slots[show].tracking) {
    uint32_t m = slots[show].cntDown / 60;
    uint32_t s = slots[show].cntDown % 60;
    snprintf(r1, 17, "SLOT %d AKTIF    ", show+1);
    snprintf(r2, 17, "Sisa: %02d:%02d      ", m, s);
  } else {
    snprintf(r1, 17, "SLOT %d TERPILIH ", show+1);
    snprintf(r2, 17, "Scan QRIS >Bayar");
  }
  lcd.setCursor(0, 0); lcd.print(r1);
  lcd.setCursor(0, 1); lcd.print(r2);
}

// --- Cek tombol ---
void checkButtons() {
  for (int i = 0; i < 3; i++) {
    bool pressed = (digitalRead(BTN[i]) == LOW);
    if (prevBtn[i] && !pressed) {
      delay(20);
      if (digitalRead(BTN[i]) != LOW) { prevBtn[i] = !pressed; continue; }
      if (slots[i].tracking) {
        Serial.printf("[BTN%d] Slot aktif, diabaikan.\n", i+1);
      } else {
        selSlot = i;
        tWait   = millis();
        writeSelection(i);
      }
    }
    prevBtn[i] = !pressed;
  }
}

// --- Cek timeout menunggu bayar ---
void checkWaitingTimeout() {
  if (selSlot == -1) return;
  if (slots[selSlot].tracking) { selSlot = -1; return; }
  if (millis() - tWait > I_WAIT) {
    Serial.println("[TIMEOUT] Reset seleksi.");
    StaticJsonDocument<64> sel;
    sel["slot"] = "none"; sel["status"] = "IDLE"; sel["timestamp"] = 0;
    String b; serializeJson(sel, b);
    fbPut("/system/active_selection", b);
    selSlot = -1;
  }
}

// --- Upload sensor DHT22 ---
void uploadSensor() {
  float temp = dht.readTemperature();
  float humi = dht.readHumidity();
  if (isnan(temp) || isnan(humi)) { Serial.println("[DHT] Gagal baca!"); return; }
  Serial.printf("[DHT] %.1f`C | %.0f%%\n", temp, humi);

  StaticJsonDocument<96> doc;
  doc["temperature_c"] = (float)(round(temp * 10) / 10.0);
  doc["humidity_pct"]  = (float)(round(humi * 10) / 10.0);
  String body; serializeJson(doc, body);
  fbPut("/system/sensors", body);

  if (temp > 60.0) {
    for (int i = 0; i < 3; i++) { setRelay(i, false); digitalWrite(LED_RING[i], LOW); }
    fbPut("/system/emergency_shutdown", "true");
    lcd.clear();
    lcd.setCursor(0, 0); lcd.print("!! DARURAT !!   ");
    lcd.setCursor(0, 1); lcd.print("Suhu > 60C STOP!");
    while (true) delay(1000);
  }
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  Serial.println("\n=== RANOVA Smart Plug v1.0 ===");

  for (int i = 0; i < 3; i++) {
    pinMode(BTN[i], INPUT_PULLUP);
    pinMode(LED_RING[i], OUTPUT);
    pinMode(RELAY[i], OUTPUT);
    setRelay(i, false);
    digitalWrite(LED_RING[i], LOW);
  }

  lcd.init(); lcd.backlight();
  lcd.setCursor(0, 0); lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Menghubungkan.");

  dht.begin();

  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("[WiFi] Menghubungkan");
  int att = 0;
  while (WiFi.status() != WL_CONNECTED && att++ < 30) {
    delay(500); Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] OK! IP: " + WiFi.localIP().toString());
    lcd.setCursor(0, 1); lcd.print("WiFi Terhubung! ");
  } else {
    Serial.println("\n[WiFi] GAGAL!");
    lcd.setCursor(0, 1); lcd.print("WiFi GAGAL!     ");
  }

  secureClient.setInsecure();
  delay(1500);
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1); lcd.print(" Tekan Tombol...");
  Serial.println("[SETUP] Siap!\n");
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  uint32_t now = millis();
  checkButtons();
  if (now - tPoll   >= I_POLL)   { tPoll   = now; pollFirebase();  }
  updateTimers();
  updateHardware();
  if (now - tLCD    >= I_LCD)    { tLCD    = now; updateLCD();     }
  if (now - tSensor >= I_SENSOR) { tSensor = now; uploadSensor();  }
  checkWaitingTimeout();
}