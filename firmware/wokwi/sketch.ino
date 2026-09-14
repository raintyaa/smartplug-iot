/*
 * ============================================================
 * RANOVA Smart Plug - Firmware ESP32 v1.1
 * Wokwi Simulator + Hardware Fisik
 * ============================================================
 * Pin GPIO:
 *  - Tombol+LED Ring : BTN(27,14,12) | LED(32,33,25)
 *  - Relay 4-Channel : IN1=23, IN2=19, IN3=18, IN4=26(spare)
 *  - LCD I2C         : SDA=21, SCL=22, Addr=0x27
 *  - DHT22           : GPIO 4
 *  - PZEM-004T       : RX2=GPIO16, TX2=GPIO17
 *
 * Libraries:
 *  - ArduinoJson (Benoit Blanchon)
 *  - LiquidCrystal I2C (Frank de Brabander)
 *  - DHT sensor library (Adafruit)
 *  - Adafruit Unified Sensor (Adafruit)
 *  - PZEM-004T-v30 (Jakub Mandula) [hardware fisik saja]
 * ============================================================
 */

// ============================================================
// MODE: uncomment untuk hardware fisik (relay Active-LOW + PZEM nyata)
#define WOKWI_SIM
// ============================================================

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>

// PZEM-004T hanya digunakan di hardware fisik
#ifndef WOKWI_SIM
  #include <PZEM004Tv30.h>
  PZEM004Tv30 pzem(&Serial2, 16, 17);  // RX=GPIO16, TX=GPIO17
#endif

// ============================================================
// KONFIGURASI
// ============================================================
const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASS     = "";
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// ============================================================
// PIN GPIO
// ============================================================
const int BTN[3]      = {27, 14, 12};   // Tombol Slot 1, 2, 3
const int LED_RING[3] = {32, 33, 25};   // LED Ring menyatu di tombol
const int RELAY[4]    = {23, 19, 18, 26}; // 4-channel relay (ch4 = spare)
#define DHT_PIN  4
#define DHT_TYPE DHT22

// ============================================================
// OBJEK
// ============================================================
LiquidCrystal_I2C lcd(0x27, 16, 2);
DHT               dht(DHT_PIN, DHT_TYPE);
WiFiClientSecure  secureClient;

// ============================================================
// STATE SLOT (hanya 3 slot yang digunakan dari 4 relay)
// ============================================================
struct SlotState {
  String   fbStatus = "STANDBY";
  uint32_t durSec   = 0;
  uint32_t cntDown  = 0;
  uint32_t startMs  = 0;
  bool     tracking = false;
};
SlotState slots[3];

// ============================================================
// DATA DAYA LISTRIK (PZEM-004T)
// ============================================================
struct PowerData {
  float voltage  = 220.0;
  float current  = 0.0;
  float power    = 0.0;
  float energy   = 0.0;   // kWh akumulatif
  float freq     = 50.0;
  float pf       = 1.0;
};
PowerData pwr;
float simEnergy = 0.0;   // Akumulasi kWh untuk simulasi Wokwi

// ============================================================
// TIMING
// ============================================================
int      selSlot     = -1;
bool     prevBtn[3]  = {true, true, true};
uint32_t tPoll       = 0;
uint32_t tSensor     = 0;
uint32_t tPower      = 0;
uint32_t tLCD        = 0;
uint32_t tBlink      = 0;
uint32_t tWait       = 0;
bool     blinkOn     = false;

const uint32_t I_POLL   = 2000;
const uint32_t I_SENSOR = 20000;
const uint32_t I_POWER  = 5000;    // Upload data daya tiap 5 detik
const uint32_t I_LCD    = 500;
const uint32_t I_BLINK  = 400;
const uint32_t I_WAIT   = 300000;

// ============================================================
// RELAY: bedakan logika Wokwi vs hardware fisik
// ============================================================
void setRelay(int idx, bool on) {
#ifdef WOKWI_SIM
  digitalWrite(RELAY[idx], on ? HIGH : LOW);   // LED biasa: HIGH = ON
#else
  digitalWrite(RELAY[idx], on ? LOW : HIGH);   // Optocoupler Active-LOW
#endif
}

// ============================================================
// FIREBASE: GET
// ============================================================
String fbGet(const String& path) {
  if (WiFi.status() != WL_CONNECTED) return "null";
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + path + ".json");
  http.setTimeout(6000);
  int code = http.GET();
  String res = "null";
  if (code == HTTP_CODE_OK) res = http.getString();
  else Serial.printf("[GET %d] %s\n", code, path.c_str());
  http.end();
  return res;
}

// ============================================================
// FIREBASE: PUT
// ============================================================
bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  http.begin(secureClient, String(FIREBASE_HOST) + path + ".json");
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(6000);
  int code = http.PUT(body);
  http.end();
  return (code == HTTP_CODE_OK || code == 200);
}

// ============================================================
// FUNGSI: Tulis seleksi tombol ke Firebase
// ============================================================
void writeSelection(int idx) {
  StaticJsonDocument<128> doc;
  doc["slot"]      = "slot" + String(idx + 1);
  doc["status"]    = "WAITING_PAYMENT";
  doc["timestamp"] = 0;  // 0 = bypass timeout check
  String body;
  serializeJson(doc, body);
  if (fbPut("/system/active_selection", body))
    Serial.printf("[BTN] Slot %d -> WAITING_PAYMENT\n", idx + 1);
}

// ============================================================
// FUNGSI: Reset slot ke STANDBY di Firebase
// ============================================================
void resetSlotFirebase(int idx) {
  String path = "/slots/slot" + String(idx + 1);
  StaticJsonDocument<128> doc;
  doc["status"]           = "STANDBY";
  doc["duration_seconds"] = 0;
  doc["amount_paid"]      = 0;
  doc["activated_at"]     = 0;
  doc["expires_at"]       = 0;
  String body;
  serializeJson(doc, body);
  fbPut(path, body);

  StaticJsonDocument<64> sel;
  sel["slot"] = "none"; sel["status"] = "IDLE"; sel["timestamp"] = 0;
  String selBody;
  serializeJson(sel, selBody);
  fbPut("/system/active_selection", selBody);
  Serial.printf("[SLOT %d] Reset STANDBY.\n", idx + 1);
}

// ============================================================
// FUNGSI: Poll semua slot dari Firebase
// ============================================================
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
      successSlot = i;
      tSuccessMsg = millis();
      Serial.printf("[SLOT %d] AKTIF! %.1f menit\n", i+1, newDur/60.0);
    }

    // Transisi ACTIVE -> lain (dari luar/Firebase)
    if (slots[i].tracking && newStatus != "ACTIVE") {
      slots[i].tracking = false;
      slots[i].cntDown  = 0;
      setRelay(i, false);
      digitalWrite(LED_RING[i], LOW);
      Serial.printf("[SLOT %d] Dihentikan dari Firebase.\n", i+1);
    }

    slots[i].fbStatus = newStatus;
    slots[i].durSec   = newDur;
  }
}

// ============================================================
// FUNGSI: Update countdown timer
// ============================================================
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

// ============================================================
// FUNGSI: Update relay & LED ring
// ============================================================
void updateHardware() {
  uint32_t now = millis();
  if (now - tBlink >= I_BLINK) { tBlink = now; blinkOn = !blinkOn; }

  for (int i = 0; i < 3; i++) {
    if (slots[i].tracking && slots[i].cntDown > 0) {
      setRelay(i, true);
      digitalWrite(LED_RING[i], HIGH);          // LED solid = AKTIF
    } else if (selSlot == i) {
      setRelay(i, false);
      digitalWrite(LED_RING[i], blinkOn ? HIGH : LOW);  // Berkedip = menunggu bayar
    } else {
      setRelay(i, false);
      digitalWrite(LED_RING[i], LOW);           // Mati = standby
    }
  }
  // Relay channel 4 (spare) selalu OFF
  setRelay(3, false);
}

// --- STATE TAMPILAN NOTIFIKASI SUKSES ---
int      successSlot   = -1;       // Slot yang baru saja berhasil dibayar
uint32_t tSuccessMsg   = 0;        // Waktu mulai pesan sukses tampil
const uint32_t I_SUCCESS_MSG = 4000; // Tampilkan selama 4 detik lalu kembali ke IDLE

// ============================================================
// FUNGSI: Update LCD 16x2
// ============================================================
void updateLCD() {
  uint32_t now = millis();
  char r1[17], r2[17];

  // 1. Prioritas Utama: Tampilkan notifikasi "BAYAR BERHASIL" selama 4 detik
  if (successSlot != -1) {
    if (now - tSuccessMsg < I_SUCCESS_MSG) {
      snprintf(r1, 17, " BAYAR BERHASIL ");
      snprintf(r2, 17, "  SLOT %d AKTIF  ", successSlot + 1);
      lcd.setCursor(0, 0); lcd.print(r1);
      lcd.setCursor(0, 1); lcd.print(r2);
      return;
    } else {
      successSlot = -1; // Selesai 4 detik, reset kembali ke normal
    }
  }

  // 2. Prioritas Kedua: Ada pengguna yang sedang memilih slot (menunggu pembayaran)
  if (selSlot != -1) {
    snprintf(r1, 17, "SLOT %d TERPILIH ", selSlot + 1);
    snprintf(r2, 17, "Scan QRIS >Bayar");
  } 
  // 3. Standby / Idle: Siap digunakan oleh pengguna berikutnya
  else {
    snprintf(r1, 17, "  RANOVA PLUG   ");
    snprintf(r2, 17, " Tekan Tombol...");
  }

  lcd.setCursor(0, 0); lcd.print(r1);
  lcd.setCursor(0, 1); lcd.print(r2);
}

// ============================================================
// FUNGSI: Baca & simulasi data PZEM-004T
// ============================================================
void readPower() {
#ifdef WOKWI_SIM
  // Simulasi nilai realistis berdasarkan relay yang aktif
  bool anyOn = false;
  int  activeCount = 0;
  for (int i = 0; i < 3; i++) if (slots[i].tracking) { anyOn = true; activeCount++; }

  pwr.voltage = 219.0 + (random(-8, 8) * 0.1f);
  pwr.freq    = 50.0f;

  if (anyOn) {
    // Simulasi: ~0.3-1.0A per slot aktif
    pwr.current = activeCount * (0.3f + (random(0, 70) * 0.01f));
    pwr.pf      = 0.85f + (random(0, 10) * 0.01f);
    pwr.power   = pwr.voltage * pwr.current * pwr.pf;
    // Akumulasi energy per interval (5 detik)
    simEnergy  += (pwr.power * I_POWER / 1000.0f) / 3600000.0f;
    pwr.energy  = simEnergy;
  } else {
    pwr.current = 0.0f;
    pwr.power   = 0.0f;
    pwr.pf      = 1.0f;
    pwr.energy  = simEnergy;
  }
#else
  // Baca dari sensor PZEM-004T yang terpasang di hardware fisik
  float v = pzem.voltage();
  if (!isnan(v)) {
    pwr.voltage = v;
    pwr.current = pzem.current();
    pwr.power   = pzem.power();
    pwr.energy  = pzem.energy();
    pwr.freq    = pzem.frequency();
    pwr.pf      = pzem.pf();
  } else {
    Serial.println("[PZEM] Gagal baca sensor daya!");
  }
#endif
}

// ============================================================
// FUNGSI: Upload data daya ke Firebase
// ============================================================
void uploadPower() {
  readPower();

  DynamicJsonDocument doc(256);
  doc["voltage_v"]  = round(pwr.voltage * 10) / 10.0f;
  doc["current_a"]  = round(pwr.current * 100) / 100.0f;
  doc["power_w"]    = round(pwr.power * 10) / 10.0f;
  doc["energy_kwh"] = pwr.energy;
  doc["freq_hz"]    = pwr.freq;
  doc["pf"]         = round(pwr.pf * 100) / 100.0f;

  String body;
  serializeJson(doc, body);
  fbPut("/system/power", body);

  Serial.printf("[PZEM] %.1fV | %.2fA | %.1fW | %.5fkWh | PF:%.2f\n",
                pwr.voltage, pwr.current, pwr.power, pwr.energy, pwr.pf);
}

// ============================================================
// FUNGSI: Upload sensor DHT22
// ============================================================
void uploadSensor() {
  float temp = dht.readTemperature();
  float humi = dht.readHumidity();
  if (isnan(temp) || isnan(humi)) { Serial.println("[DHT] Gagal baca!"); return; }

  Serial.printf("[DHT] %.1fC | %.0f%%\n", temp, humi);

  StaticJsonDocument<96> doc;
  doc["temperature_c"] = round(temp * 10) / 10.0f;
  doc["humidity_pct"]  = round(humi);
  String body;
  serializeJson(doc, body);
  fbPut("/system/sensors", body);

  // Emergency shutdown suhu > 60 C
  if (temp > 60.0f) {
    Serial.println("[!!!] DARURAT! Suhu > 60 derajat!");
    for (int i = 0; i < 4; i++) setRelay(i, false);
    for (int i = 0; i < 3; i++) digitalWrite(LED_RING[i], LOW);
    fbPut("/system/emergency_shutdown", "true");
    lcd.clear();
    lcd.setCursor(0, 0); lcd.print("!! DARURAT !!   ");
    lcd.setCursor(0, 1); lcd.print("Suhu > 60C STOP!");
    while (true) delay(1000);
  }
}

// ============================================================
// FUNGSI: Cek tombol
// ============================================================
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

// ============================================================
// FUNGSI: Cek timeout menunggu bayar
// ============================================================
void checkWaitingTimeout() {
  if (selSlot == -1) return;
  if (slots[selSlot].tracking) { selSlot = -1; return; }
  if (millis() - tWait > I_WAIT) {
    Serial.println("[TIMEOUT] Reset seleksi slot.");
    StaticJsonDocument<64> sel;
    sel["slot"] = "none"; sel["status"] = "IDLE"; sel["timestamp"] = 0;
    String b; serializeJson(sel, b);
    fbPut("/system/active_selection", b);
    selSlot = -1;
  }
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  Serial.println("\n=== RANOVA Smart Plug v1.1 ===");

  // Init semua relay (4 channel) dan LED ring (3 buah)
  for (int i = 0; i < 4; i++) {
    pinMode(RELAY[i], OUTPUT);
    setRelay(i, false);  // Semua relay OFF saat boot
  }
  for (int i = 0; i < 3; i++) {
    pinMode(BTN[i], INPUT_PULLUP);
    pinMode(LED_RING[i], OUTPUT);
    digitalWrite(LED_RING[i], LOW);
  }

  lcd.init(); lcd.backlight();
  lcd.setCursor(0, 0); lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1); lcd.print("  Menghubungkan.");

  dht.begin();

  // PZEM-004T Serial2 (hanya hardware fisik)
  #ifndef WOKWI_SIM
    Serial2.begin(9600, SERIAL_8N1, 16, 17);
    Serial.println("[PZEM] Serial2 GPIO16(RX)/17(TX) siap.");
  #else
    Serial.println("[PZEM] Mode simulasi Wokwi aktif.");
  #endif

  // Koneksi WiFi
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

  secureClient.setInsecure();  // Skip SSL cert (dev mode)
  delay(1500);

  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1); lcd.print(" Tekan Tombol...");
  Serial.println("[SETUP] Sistem siap!\n");
}

// ============================================================
// LOOP UTAMA
// ============================================================
void loop() {
  uint32_t now = millis();

  // Cek tombol (setiap loop)
  checkButtons();

  // Poll status slot dari Firebase (tiap 2 detik)
  if (now - tPoll >= I_POLL) {
    tPoll = now;
    pollFirebase();
  }

  // Update countdown timer
  updateTimers();

  // Update relay & LED ring
  updateHardware();

  // Refresh LCD (tiap 0.5 detik)
  if (now - tLCD >= I_LCD) {
    tLCD = now;
    updateLCD();
  }

  // Upload data daya PZEM ke Firebase (tiap 5 detik)
  if (now - tPower >= I_POWER) {
    tPower = now;
    uploadPower();
  }

  // Upload sensor DHT22 ke Firebase (tiap 20 detik)
  if (now - tSensor >= I_SENSOR) {
    tSensor = now;
    uploadSensor();
  }

  // Cek timeout menunggu bayar
  checkWaitingTimeout();
}