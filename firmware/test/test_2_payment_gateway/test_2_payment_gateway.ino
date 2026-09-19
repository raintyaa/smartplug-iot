/*
 * ==============================================================================
 * RANOVA Smart Plug - TAHAP 2: Uji End-to-End Single-Slot ke Cloud & Payment
 * ==============================================================================
 * Komponen yang Diuji:
 *   - ESP32 DevKit V1 (Koneksi WiFi ke Internet)
 *   - 1x Tombol Metal 16mm (Slot 1 di GPIO 27)
 *   - 1x LED Ring Tombol (Slot 1 di GPIO 32)
 *   - 1x Relay 4-Channel (Channel 1 di GPIO 23, Active-LOW)
 *   - Firebase Realtime Database
 *   - Webhook Vercel + Mayar QRIS
 *   *(LCD DI-EXCLUDE SEMENTARA)*
 *
 * Alur Kerja:
 *   1. Nyala (STANDBY): Relay OFF, LED OFF.
 *   2. Tekan Tombol 1: ESP32 update Firebase -> WAITING_PAYMENT, LED Ring KEDIP.
 *   3. Pengguna scan QRIS Mayar di HP -> Bayar (Rp 1.000).
 *   4. Webhook Vercel terima bayaran -> update Firebase -> ACTIVE (durasi 900 detik).
 *   5. ESP32 deteksi status ACTIVE -> Relay 1 "KLIK" ON, LED Ring SOLID ON!
 *   6. Countdown timer berjalan -> Saat habis -> Relay "KLIK" OFF, reset STANDBY.
 * ==============================================================================
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ==============================================================================
// 1. PENGATURAN WIFI (GANTI DENGAN HOTSPOT / WIFI KAMU)
// ==============================================================================
const char* WIFI_SSID = "RedmiNote12";
const char* WIFI_PASS = "012345678";

// Firebase RTDB URL (Asia-Southeast1)
const char* FIREBASE_HOST = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app";

// ==============================================================================
// 2. PIN GPIO HARDWARE
// ==============================================================================
const int PIN_BTN   = 27;  // Tombol Slot 1 (INPUT_PULLUP)
const int PIN_LED   = 32;  // LED Ring Slot 1 (OUTPUT)
const int PIN_RELAY = 23;  // Relay Channel 1 (OUTPUT, Active-LOW: LOW=ON, HIGH=OFF)

// ==============================================================================
// 3. STATE & TIMING
// ==============================================================================
enum SystemState { STATE_STANDBY, STATE_WAITING_PAYMENT, STATE_ACTIVE };
SystemState currentState = STATE_STANDBY;

uint32_t activeDuration = 0;   // Durasi sewa dalam detik
uint32_t activeStartMs  = 0;   // Waktu mulai aktif (millis)
uint32_t lastPollMs     = 0;   // Waktu polling Firebase terakhir
uint32_t lastBlinkMs    = 0;   // Waktu kedip LED terakhir
uint32_t lastPrintSecMs = 0;   // Waktu cetak countdown per detik
bool     blinkState     = false;

const uint32_t POLL_INTERVAL  = 2000;  // Cek Firebase tiap 2 detik
const uint32_t BLINK_INTERVAL = 400;   // Kedip tiap 400ms saat menunggu bayar
const uint32_t WAIT_TIMEOUT   = 300000; // 5 menit timeout jika tidak dibayar
uint32_t waitStartMs = 0;

WiFiClientSecure secureClient;

// ==============================================================================
// 4. KONTROL RELAY & LED
// ==============================================================================
void setRelay(bool on) {
  // Modul Relay Active-LOW: LOW = ON, HIGH = OFF
  digitalWrite(PIN_RELAY, on ? LOW : HIGH);
}

void setLed(bool on) {
  digitalWrite(PIN_LED, on ? HIGH : LOW);
}

// ==============================================================================
// 5. HELPER FIREBASE (HTTP GET & PUT)
// ==============================================================================
String fbGet(const String& path) {
  if (WiFi.status() != WL_CONNECTED) return "null";
  HTTPClient http;
  String url = String(FIREBASE_HOST) + path + ".json";
  http.begin(secureClient, url);
  http.setTimeout(5000);
  int code = http.GET();
  String res = "null";
  if (code == HTTP_CODE_OK || code == 200) {
    res = http.getString();
  }
  http.end();
  return res;
}

bool fbPut(const String& path, const String& body) {
  if (WiFi.status() != WL_CONNECTED) return false;
  HTTPClient http;
  String url = String(FIREBASE_HOST) + path + ".json";
  http.begin(secureClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);
  int code = http.PUT(body);
  http.end();
  return (code == HTTP_CODE_OK || code == 200);
}

// ==============================================================================
// 6. FUNGSI LOGIKA SISTEM
// ==============================================================================

// Kirim sinyal bahwa Tombol 1 ditekan & sedang menunggu pembayaran
void triggerWaitingPayment() {
  currentState = STATE_WAITING_PAYMENT;
  setRelay(false);

  Serial.println("\n[ACTION] Tombol Slot 1 ditekan!");
  Serial.println(">> Mengirim status WAITING_PAYMENT ke Firebase...");

  StaticJsonDocument<128> doc;
  doc["slot"]      = "slot1";
  doc["status"]    = "WAITING_PAYMENT";
  doc["timestamp"] = 0; // 0 = bypass timeout di webhook

  String body;
  serializeJson(doc, body);
  if (fbPut("/system/active_selection", body)) {
    Serial.println("[FIREBASE] Berhasil! active_selection = WAITING_PAYMENT.");
    Serial.println(">> SILAKAN SCAN QRIS MAYAR DI HP DAN BAYAR RP 1.000 SEKARANG.");
    Serial.println(">> Lampu LED ring mulai berkedip menunggu pembayaran...\n");
  } else {
    Serial.println("[FIREBASE] Gagal menghubungi Firebase!");
  }

  // Set waktu mulai menunggu SETELAH pengiriman Firebase selesai
  waitStartMs = millis();
}

// Reset slot kembali ke Standby
void resetToStandby() {
  currentState = STATE_STANDBY;
  waitStartMs  = 0;
  setRelay(false);
  setLed(false);

  Serial.println("\n[SISTEM] Waktu sewa habis atau reset! Mematikan Relay 1...");

  // Reset node slot1 di Firebase
  StaticJsonDocument<128> slotDoc;
  slotDoc["status"]           = "STANDBY";
  slotDoc["duration_seconds"] = 0;
  slotDoc["amount_paid"]      = 0;
  slotDoc["activated_at"]     = 0;
  slotDoc["expires_at"]       = 0;
  String slotBody;
  serializeJson(slotDoc, slotBody);
  fbPut("/slots/slot1", slotBody);

  // Reset active_selection ke IDLE
  StaticJsonDocument<64> selDoc;
  selDoc["slot"]      = "none";
  selDoc["status"]    = "IDLE";
  selDoc["timestamp"] = 0;
  String selBody;
  serializeJson(selDoc, selBody);
  fbPut("/system/active_selection", selBody);

  Serial.println("[FIREBASE] Status slot1 di-reset ke STANDBY.");
  Serial.println("[READY] Sistem kembali STANDBY. Siap ditekan lagi!\n");
}

// Cek status slot1 di Firebase
void pollFirebase() {
  String res = fbGet("/slots/slot1");
  if (res == "null" || res.length() < 5) return;

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, res);
  if (err) return;

  String status   = doc["status"] | "STANDBY";
  uint32_t durSec = doc["duration_seconds"] | 0;

  // JIKA DETEKSI PEMBAYARAN MASUK (STANDBY / WAITING -> ACTIVE)
  if (status == "ACTIVE" && currentState != STATE_ACTIVE) {
    currentState   = STATE_ACTIVE;
    activeDuration = durSec;
    activeStartMs  = millis();

    // AKTIFKAN RELAY & LED SOLID
    setRelay(true);
    setLed(true);

    Serial.println("\n=======================================================");
    Serial.println(" 🎉 [WEBHOOK / FIREBASE] PEMBAYARAN QRIS TERKONFIRMASI! ");
    Serial.printf (" >> SLOT 1 AKTIF! Durasi sewa: %d detik (%.1f menit)\n", durSec, durSec / 60.0);
    Serial.println(" >> [RELAY 1] >>> KLIK! RELAY MENYALA (Stop Kontak Hidup)! <<<");
    Serial.println(" >> [LED RING] MENYALA SOLID.");
    Serial.println("=======================================================\n");
  }

  // Jika dimatikan manual dari Firebase
  if (status == "STANDBY" && currentState == STATE_ACTIVE) {
    resetToStandby();
  }
}

// ==============================================================================
// 7. SETUP
// ==============================================================================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n============================================================");
  Serial.println("  RANOVA SMART PLUG - UJI SINGLE-SLOT CLOUD & PAYMENT QRIS  ");
  Serial.println("============================================================");

  pinMode(PIN_BTN, INPUT_PULLUP);
  pinMode(PIN_LED, OUTPUT);
  pinMode(PIN_RELAY, OUTPUT);

  setRelay(false);
  setLed(false);

  // TES AWAL LED: Kedipkan 3 kali agar tahu kabel LED terhubung benar
  Serial.println("\n[TES FISIK] Menguji LED Ring (kedip 3x)...");
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_LED, HIGH);
    delay(200);
    digitalWrite(PIN_LED, LOW);
    delay(200);
  }
  Serial.println("[TES FISIK] Tes LED selesai.");

  // Sambungkan ke WiFi
  Serial.printf("[WiFi] Menghubungkan ke '%s'", WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts++ < 30) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] TERHUBUNG! IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n[WiFi] GAGAL! Periksa nama SSID & Password di kode.");
  }

  // Skip verifikasi sertifikat SSL
  secureClient.setInsecure();

  Serial.println("\n[SETUP SELESAI] Sistem siap diuji!");
  Serial.println(">> LANGKAH 1: TEKAN TOMBOL FISIK SLOT 1 SEKARANG! <<\n");
}

// ==============================================================================
// 8. LOOP UTAMA
// ==============================================================================
void loop() {
  uint32_t now = millis();

  // 1. Cek tombol fisik (hanya jika sedang STANDBY)
  if (currentState == STATE_STANDBY) {
    if (digitalRead(PIN_BTN) == LOW) {
      delay(30); // Debounce
      if (digitalRead(PIN_BTN) == LOW) {
        triggerWaitingPayment();
        while (digitalRead(PIN_BTN) == LOW) delay(10); // Tunggu sampai tombol dilepas
      }
    }
  }

  // 2. Animasi LED Ring saat WAITING_PAYMENT (Berkedip)
  if (currentState == STATE_WAITING_PAYMENT) {
    if (now - lastBlinkMs >= BLINK_INTERVAL) {
      lastBlinkMs = now;
      blinkState = !blinkState;
      setLed(blinkState);
    }

    // Timeout jika tidak dibayar dalam 5 menit
    if (waitStartMs > 0 && (millis() - waitStartMs > WAIT_TIMEOUT)) {
      Serial.println("[TIMEOUT] Melebihi 5 menit tidak dibayar. Kembali ke Standby.");
      resetToStandby();
    }
  }

  // 3. Polling Firebase tiap 2 detik
  if (now - lastPollMs >= POLL_INTERVAL) {
    lastPollMs = now;
    pollFirebase();
  }

  // 4. Hitung mundur waktu saat ACTIVE
  if (currentState == STATE_ACTIVE) {
    uint32_t elapsedSec = (now - activeStartMs) / 1000;

    if (elapsedSec >= activeDuration) {
      // Durasi habis!
      resetToStandby();
    } else {
      // Cetak sisa waktu ke Serial Monitor tiap 5 detik
      if (now - lastPrintSecMs >= 5000) {
        lastPrintSecMs = now;
        uint32_t sisaSec = activeDuration - elapsedSec;
        Serial.printf("[COUNTDOWN] Slot 1 Aktif | Sisa Waktu: %02d:%02d\n", sisaSec / 60, sisaSec % 60);
      }
    }
  }
}