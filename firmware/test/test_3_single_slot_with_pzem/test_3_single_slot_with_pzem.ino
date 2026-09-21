// ============================================================
// RANOVA Smart Plug - Uji Terpadu 1 Slot + PZEM + DHT22 + LCD (Tes 3)
// ============================================================
// Fitur:
// 1. Tombol 1 (GPIO 27) & LED Ring 1 (GPIO 32)
// 2. Relay 1 (Active-LOW, GPIO 23) ke Stop Kontak Broco 1
// 3. LCD 16x2 I2C (0x27, SDA 21, SCL 22) - Tampilan Event-Based & Watt Realtime
// 4. Sensor Daya PZEM-004T v3.0 (RX2 16, TX2 17) - Pengukuran Volt, Ampere, Watt, kWh
// 5. Sensor Suhu DHT22 (GPIO 4) - Proteksi Overheat (> 60°C)
// 6. Sinkronisasi Realtime ke Firebase RTDB & Web Dashboard Laravel
// ============================================================

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <PZEM004Tv30.h>

// --- 1. KONFIGURASI WIFI & FIREBASE ---
const char* WIFI_SSID     = "RedmiNote12";        // Ganti dengan nama WiFi/Hotspot kamu
const char* WIFI_PASSWORD = "012345678";    // Ganti dengan password WiFi kamu
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// --- 2. PIN HARDWARE ESP32 ---
#define PIN_BUTTON_1    27   // Tombol Metal Slot 1 (INPUT_PULLUP)
#define PIN_LED_1       32   // LED Ring Slot 1 (OUTPUT)
#define PIN_RELAY_1     23   // Relay Slot 1 (Active-LOW, LOW=ON, HIGH=OFF)

#define PIN_DHT         4    // Data Sensor DHT22
#define DHTTYPE         DHT22

#define PZEM_RX_PIN     16   // ESP32 RX2 -> Terhubung ke Pin TX PZEM
#define PZEM_TX_PIN     17   // ESP32 TX2 -> Terhubung ke Pin RX PZEM

#define LCD_ADDR        0x27
#define LCD_COLS        16
#define LCD_ROWS        2

#define OVERHEAT_LIMIT  60.0 // Batas Darurat Suhu Box (°C)
#define QRIS_TIMEOUT_MS 60000 // Timeout 60 detik menunggu bayar

// Inisialisasi Objek
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT dht(PIN_DHT, DHTTYPE);
PZEM004Tv30 pzem(Serial2, PZEM_RX_PIN, PZEM_TX_PIN);

// --- 3. STATE MACHINE SLOT 1 ---
enum SlotState { STANDBY, WAITING_PAYMENT, ACTIVE, EMERGENCY_STOP };
SlotState currentState = STANDBY;

// Variabel Waktu & Durasi
unsigned long waitStartMs   = 0;
unsigned long activeStartMs = 0;
unsigned long activeDurationSec = 0;

// Pewaktu Interval (Non-blocking)
unsigned long lastFirebasePollMs   = 0;
unsigned long lastTelemetrySyncMs  = 0;
unsigned long lastBlinkMs          = 0;
unsigned long lastLcdUpdateMs      = 0;
bool ledBlinkState = false;

// Variabel Data Sensor Terakhir
float currentVoltage = 0.0;
float currentAmps    = 0.0;
float currentWatts   = 0.0;
float currentEnergy  = 0.0;
float currentTemp    = 0.0;
float currentHum     = 0.0;

// Debounce Tombol
int lastButtonReading = HIGH;
unsigned long lastDebounceTime = 0;
const unsigned long DEBOUNCE_DELAY = 50;

WiFiClientSecure secureClient;

// Deklarasi Fungsi
void connectWiFi();
void checkButton();
void pollFirebase();
void updateTelemetry();
void updateLcdDisplay();
void handleEmergencyOverheat();
void setSlotStandby();

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n=======================================================");
  Serial.println("  RANOVA SMART PLUG - UJI TERPADU (SLOT 1 + PZEM + DHT)");
  Serial.println("=======================================================");

  // 1. Setup Pin GPIO
  pinMode(PIN_BUTTON_1, INPUT_PULLUP);
  pinMode(PIN_LED_1, OUTPUT);
  pinMode(PIN_RELAY_1, OUTPUT);

  // Default Aman: Relay OFF (HIGH), LED OFF
  digitalWrite(PIN_RELAY_1, HIGH);
  digitalWrite(PIN_LED_1, LOW);

  // 2. Setup LCD I2C
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("RANOVA SMARTPLUG");
  lcd.setCursor(0, 1);
  lcd.print("Memulai Sistem..");

  // 3. Setup Sensor DHT22
  dht.begin();
  Serial.println("[INFO] Sensor DHT22 diinisialisasi pada GPIO 4.");

  // 4. Setup PZEM-004T
  Serial.println("[INFO] Sensor PZEM-004T diinisialisasi pada Serial2 (RX=16, TX=17).");

  // 5. Setup SSL Client (Bypass sertifikat agar cepat)
  secureClient.setInsecure();

  // 6. Hubungkan ke WiFi
  connectWiFi();

  // Bersihkan status awal slot di Firebase
  setSlotStandby();

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("RANOVA PLUG 1");
  lcd.setCursor(0, 1);
  lcd.print("Tekan Tombol..");
  Serial.println("\n[SIAP] Sistem dalam mode STANDBY.");
  Serial.println(">> TEKAN TOMBOL METAL 1 UNTUK MEMULAI SEWA! <<\n");
}

void loop() {
  unsigned long now = millis();

  // 1. Cek Suhu Darurat (DHT22)
  handleEmergencyOverheat();
  if (currentState == EMERGENCY_STOP) return;

  // 2. Tangani Tombol Fisik (Instant & Responsive)
  checkButton();

  // 3. Tangani State LED saat Menunggu Bayar
  if (currentState == WAITING_PAYMENT) {
    if (millis() - lastBlinkMs >= 500) {
      lastBlinkMs = millis();
      ledBlinkState = !ledBlinkState;
      digitalWrite(PIN_LED_1, ledBlinkState ? HIGH : LOW);
    }

    // Cek Timeout 60 Detik (Gunakan millis() segar agar tidak terjadi underflow!)
    if (millis() - waitStartMs >= QRIS_TIMEOUT_MS) {
      Serial.println("[TIMEOUT] Waktu 60 detik habis. Belum ada pembayaran. Kembali ke STANDBY.");
      setSlotStandby();
    }
  }

  // 4. Polling Status Firebase (HANYA saat menunggu bayar atau sedang aktif)
  if (currentState == WAITING_PAYMENT || currentState == ACTIVE) {
    if (now - lastFirebasePollMs >= 1500) {
      lastFirebasePollMs = now;
      pollFirebase();
    }
  }

  // 5. Update Pembacaan PZEM & Kirim Telemetri ke Firebase (setiap 3 detik)
  if (now - lastTelemetrySyncMs >= 3000) {
    lastTelemetrySyncMs = now;
    updateTelemetry();
  }

  // 6. Update Tampilan LCD (setiap 500ms agar countdown & watt halus)
  if (now - lastLcdUpdateMs >= 500) {
    lastLcdUpdateMs = now;
    updateLcdDisplay();
  }

  // 7. Cek apakah sewa aktif telah habis waktunya
  if (currentState == ACTIVE) {
    unsigned long elapsedSec = (millis() - activeStartMs) / 1000;
    if (elapsedSec >= activeDurationSec) {
      Serial.println("[SELESAI] Waktu sewa telah habis! Mematikan relay...");
      setSlotStandby();
    }
  }
}

// ============================================================
// FUNGSI: Pembacaan Tombol & Debounce Teruji
// ============================================================
void checkButton() {
  if (digitalRead(PIN_BUTTON_1) == LOW) {
    delay(40); // Debounce stabil
    if (digitalRead(PIN_BUTTON_1) == LOW) {
      if (currentState == STANDBY) {
        Serial.println("\n[EVENT] Tombol 1 Ditekan -> Mengajukan Sewa QRIS...");
        currentState = WAITING_PAYMENT;
        waitStartMs = millis(); // Refresh waktu tunggu segar

        // Update Firebase: minta QRIS untuk Slot 1
        HTTPClient http;
        String url = String(FIREBASE_HOST) + "/system/active_selection.json";
        http.begin(secureClient, url);
        http.addHeader("Content-Type", "application/json");
        http.setTimeout(4000);

        String payload = "{\"slot\":\"slot1\",\"status\":\"WAITING_PAYMENT\",\"timestamp\":" + String(millis()) + "}";
        http.PUT(payload);
        http.end();

        // Update slot1 state
        url = String(FIREBASE_HOST) + "/slot1.json";
        http.begin(secureClient, url);
        http.addHeader("Content-Type", "application/json");
        http.setTimeout(4000);
        http.PUT("{\"status\":\"WAITING_PAYMENT\",\"active_duration\":0,\"started_at\":0}");
        http.end();

        Serial.println("[FIREBASE] Status WAITING_PAYMENT terkirim. Silakan scan & bayar QRIS!");

        // Tunggu sampai tombol dilepas
        while (digitalRead(PIN_BUTTON_1) == LOW) delay(10);
      }
      else if (currentState == ACTIVE) {
        // Fitur Early Stop: Tekan tombol saat aktif mematikan sewa seketika
        Serial.println("\n[EVENT] Early Stop: Pengguna menekan tombol untuk mematikan colokan lebih awal.");
        setSlotStandby();

        // Tunggu sampai tombol dilepas
        while (digitalRead(PIN_BUTTON_1) == LOW) delay(10);
      }
    }
  }
}

// ============================================================
// FUNGSI: Polling Status dari Firebase RTDB
// ============================================================
void pollFirebase() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = String(FIREBASE_HOST) + "/slot1.json";
  http.begin(secureClient, url);
  http.setTimeout(3000);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String response = http.getString();
    StaticJsonDocument<256> doc;
    DeserializationError error = deserializeJson(doc, response);

    if (!error) {
      const char* status = doc["status"];

      // Jika dari Webhook atau Web diubah menjadi ACTIVE
      if (strcmp(status, "ACTIVE") == 0 && currentState != ACTIVE) {
        currentState = ACTIVE;
        activeDurationSec = doc["active_duration"] | 900; // default 15 menit
        activeStartMs = millis(); // Set waktu mulai sekarang yang segar!

        // Nyalakan Relay 1 (Active-LOW: LOW) & LED Solid ON
        digitalWrite(PIN_RELAY_1, LOW);
        digitalWrite(PIN_LED_1, HIGH);

        Serial.println("\n=================================================");
        Serial.println("  PEMBAYARAN DITERIMA! RELAY 1 DIAKTIFKAN!       ");
        Serial.print("  Durasi Sewa: ");
        Serial.print(activeDurationSec / 60);
        Serial.println(" Menit");
        Serial.println("=================================================");
      }
      // Jika dari Web Dashboard ditekan "Matikan Manual" (status kembali ke STANDBY)
      else if (strcmp(status, "STANDBY") == 0 && currentState == ACTIVE) {
        Serial.println("[REMOTE] Web Dashboard mematikan Slot 1 secara manual.");
        setSlotStandby();
      }
    }
  }
  http.end();
}

// ============================================================
// FUNGSI: Pembacaan Sensor PZEM-004T & Telemetri
// ============================================================
void updateTelemetry() {
  // 1. Baca PZEM-004T
  float v = pzem.voltage();
  float i = pzem.current();
  float p = pzem.power();
  float e = pzem.energy();

  if (!isnan(v)) currentVoltage = v;
  if (!isnan(i)) currentAmps    = i;
  if (!isnan(p)) currentWatts   = p;
  if (!isnan(e)) currentEnergy  = e;

  // 2. Baca DHT22
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  if (!isnan(t)) currentTemp = t;
  if (!isnan(h)) currentHum  = h;

  // Log ke Serial Monitor
  Serial.print("[PZEM] V: ");
  Serial.print(currentVoltage, 1);
  Serial.print("V | I: ");
  Serial.print(currentAmps, 2);
  Serial.print("A | P: ");
  Serial.print(currentWatts, 1);
  Serial.print("W | Energy: ");
  Serial.print(currentEnergy, 4);
  Serial.print(" kWh | Suhu: ");
  Serial.print(currentTemp, 1);
  Serial.println("°C");

  // 3. Kirim Telemetri ke Firebase agar Dashboard Laravel ikut ter-update
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = String(FIREBASE_HOST) + "/sensors.json";
    http.begin(secureClient, url);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(3000);

    String jsonPayload = "{";
    jsonPayload += "\"voltage\":" + String(currentVoltage, 1) + ",";
    jsonPayload += "\"current\":" + String(currentAmps, 2) + ",";
    jsonPayload += "\"power\":" + String(currentWatts, 1) + ",";
    jsonPayload += "\"energy\":" + String(currentEnergy, 4) + ",";
    jsonPayload += "\"temperature\":" + String(currentTemp, 1) + ",";
    jsonPayload += "\"humidity\":" + String(currentHum, 1) + ",";
    jsonPayload += "\"updated_at\":" + String(millis());
    jsonPayload += "}";

    http.PUT(jsonPayload);
    http.end();
  }
}

// ============================================================
// FUNGSI: Tampilan Layar LCD 16x2 (Event-Based)
// ============================================================
void updateLcdDisplay() {
  if (currentState == STANDBY) {
    lcd.setCursor(0, 0);
    lcd.print("RANOVA PLUG 1   ");
    lcd.setCursor(0, 1);
    lcd.print("Tekan Tombol..  ");
  }
  else if (currentState == WAITING_PAYMENT) {
    unsigned long elapsed = (millis() - waitStartMs) / 1000;
    int remaining = max(0, (int)(QRIS_TIMEOUT_MS / 1000) - (int)elapsed);

    lcd.setCursor(0, 0);
    lcd.print("Scan QRIS Slot 1");
    lcd.setCursor(0, 1);
    lcd.print("Tunggu: ");
    if (remaining < 10) lcd.print("0");
    lcd.print(remaining);
    lcd.print("s  [QR]");
  }
  else if (currentState == ACTIVE) {
    unsigned long elapsedSec = (millis() - activeStartMs) / 1000;
    int remaining = max(0, (int)activeDurationSec - (int)elapsedSec);
    int m = remaining / 60;
    int s = remaining % 60;

    // Baris 1: Countdown Sisa Waktu
    lcd.setCursor(0, 0);
    lcd.print("S1:");
    if (m < 10) lcd.print("0");
    lcd.print(m);
    lcd.print(":");
    if (s < 10) lcd.print("0");
    lcd.print(s);
    lcd.print(" Aktif ");

    // Baris 2: Watt & Voltase Realtime dari PZEM!
    lcd.setCursor(0, 1);
    char buf[17];
    snprintf(buf, sizeof(buf), "%3.0fW %3.0fV %4.2fk", currentWatts, currentVoltage, currentEnergy);
    lcd.print(buf);
  }
  else if (currentState == EMERGENCY_STOP) {
    lcd.setCursor(0, 0);
    lcd.print("! OVERHEAT ALARM");
    lcd.setCursor(0, 1);
    lcd.print("RELAY CUTOFF ");
    lcd.print((int)currentTemp);
    lcd.print("C");
  }
}

// ============================================================
// FUNGSI: Proteksi Bahaya Overheat DHT22
// ============================================================
void handleEmergencyOverheat() {
  if (currentTemp >= OVERHEAT_LIMIT) {
    if (currentState != EMERGENCY_STOP) {
      Serial.println("\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
      Serial.println("  PERINGATAN: SUHU OVERHEAT MELAMPAUI 60.0°C!      ");
      Serial.println("  EMERGENCY SHUTDOWN: MEMATIKAN SEMUA RELAY!       ");
      Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");

      currentState = EMERGENCY_STOP;
      digitalWrite(PIN_RELAY_1, HIGH); // Matikan Relay Seketika!
      digitalWrite(PIN_LED_1, LOW);

      // Lapor ke Firebase
      if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(secureClient, String(FIREBASE_HOST) + "/system/emergency_alert.json");
        http.setTimeout(3000);
        http.PUT("true");
        http.end();
      }
    }
  }
}

// ============================================================
// FUNGSI: Reset Slot ke Standby
// ============================================================
void setSlotStandby() {
  currentState = STANDBY;
  digitalWrite(PIN_RELAY_1, HIGH); // Relay OFF
  digitalWrite(PIN_LED_1, LOW);    // LED OFF

  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = String(FIREBASE_HOST) + "/slot1.json";
    http.begin(secureClient, url);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(3000);
    http.PUT("{\"status\":\"STANDBY\",\"active_duration\":0,\"started_at\":0,\"nominal_paid\":0}");
    http.end();

    // Reset active selection
    url = String(FIREBASE_HOST) + "/system/active_selection.json";
    http.begin(secureClient, url);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(3000);
    http.PUT("{\"slot\":\"none\",\"status\":\"IDLE\",\"timestamp\":0}");
    http.end();
  }
}

// ============================================================
// FUNGSI: Koneksi WiFi
// ============================================================
void connectWiFi() {
  Serial.print("[WIFI] Menghubungkan ke ");
  Serial.print(WIFI_SSID);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 25) {
    delay(500);
    Serial.print(".");
    retry++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI] Berhasil terhubung!");
    Serial.print("[WIFI] Alamat IP ESP32: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n[WARN] WiFi gagal terhubung! Sistem tetap berjalan offline.");
  }
}
