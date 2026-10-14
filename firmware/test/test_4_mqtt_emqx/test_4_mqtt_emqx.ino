// ============================================================
// RANOVA Smart Plug - Uji Konektivitas EMQX Cloud MQTT (Tes 4)
// ============================================================
// Fitur Pengujian:
// 1. Koneksi WiFi (hujan / 02082006)
// 2. Koneksi MQTTS (TLS Port 8883) ke EMQX Cloud Serverless
// 3. Otentikasi Client (Username: ranova_esp32 & Password)
// 4. Publikasi Pesan Telemetri Berkala ke Topik: ranova/device_001/telemetry
// 5. Langganan (Subscribe) Perintah ke Topik: ranova/device_001/command
// 6. Tampilan Status di Layar LCD 16x2 I2C (0x27) & Serial Monitor
// ============================================================

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// --- 1. KONFIGURASI WIFI ---
const char* WIFI_SSID     = "hujan";          // SSID Hotspot/WiFi
const char* WIFI_PASSWORD = "02082006";       // Password WiFi

// --- 2. KONFIGURASI EMQX CLOUD BROKER ---
const char* MQTT_BROKER   = "o2f6eb8b.ala.asia-southeast1.emqxsl.com";
const int   MQTT_PORT     = 8883;             // Port MQTTS (TLS/SSL)
const char* MQTT_USER     = "ranova_esp32";
const char* MQTT_PASSWORD = "02082006";  // GANTI DENGAN PASSWORD YANG ANDA BUAT DI EMQX
const char* MQTT_CLIENT_ID= "ranova-esp32-tester";

// --- 3. TOPIK MQTT ---
const char* TOPIC_TELEMETRY = "ranova/device_001/telemetry";
const char* TOPIC_COMMAND   = "ranova/device_001/command";
const char* TOPIC_STATUS    = "ranova/device_001/status";

// --- 4. HARDWARE LCD I2C ---
#define LCD_ADDR 0x27
#define LCD_COLS 16
#define LCD_ROWS 2
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);

// --- 5. OBJEK JARINGAN & MQTT ---
WiFiClientSecure netClient;
PubSubClient mqttClient(netClient);

unsigned long lastPublishTime = 0;
const unsigned long PUBLISH_INTERVAL = 3000; // Kirim tiap 3 detik
unsigned long packetCount = 0;

// Callback saat menerima pesan dari broker
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  Serial.print("\n[MQTT RX] Topik: ");
  Serial.println(topic);
  
  String message = "";
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.print("[MQTT RX] Payload: ");
  Serial.println(message);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("MQTT CMD RX:");
  lcd.setCursor(0, 1);
  lcd.print(message.substring(0, 16));
}

void connectWiFi() {
  Serial.print("\n[WIFI] Menghubungkan ke ");
  Serial.println(WIFI_SSID);
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Connect WiFi...");
  lcd.setCursor(0, 1);
  lcd.print(WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI] Terhubung!");
    Serial.print("[WIFI] IP Address: ");
    Serial.println(WiFi.localIP());
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi Connected!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP());
    delay(1500);
  } else {
    Serial.println("\n[WIFI] Gagal terhubung!");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi GAGAL!");
  }
}

void connectMQTT() {
  while (!mqttClient.connected()) {
    Serial.print("[MQTT] Menghubungkan ke EMQX Cloud (");
    Serial.print(MQTT_BROKER);
    Serial.print(":");
    Serial.print(MQTT_PORT);
    Serial.println(")...");

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Connect EMQX...");
    lcd.setCursor(0, 1);
    lcd.print("TLS Port 8883");

    // LWT (Last Will and Testament) jika perangkat offline tiba-tiba
    const char* willTopic = TOPIC_STATUS;
    const char* willMsg = "{\"state\":\"offline\",\"client\":\"ranova-esp32\"}";
    int willQos = 1;
    bool willRetain = true;

    if (mqttClient.connect(MQTT_CLIENT_ID, MQTT_USER, MQTT_PASSWORD, willTopic, willQos, willRetain, willMsg)) {
      Serial.println("[MQTT] BERHASIL Terhubung ke EMQX Broker! 🎉");
      
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("EMQX Connected!");
      lcd.setCursor(0, 1);
      lcd.print("Ready to Pub/Sub");

      // Subscribe ke topik command
      mqttClient.subscribe(TOPIC_COMMAND);
      Serial.print("[MQTT] Subscribe ke topik: ");
      Serial.println(TOPIC_COMMAND);

      // Publish status online
      StaticJsonDocument<128> doc;
      doc["state"] = "online";
      doc["ip"] = WiFi.localIP().toString();
      doc["uptime_ms"] = millis();
      char buffer[128];
      serializeJson(doc, buffer);
      mqttClient.publish(TOPIC_STATUS, buffer, true);

      delay(1500);
    } else {
      Serial.print("[MQTT] Gagal terhubung, rc=");
      Serial.print(mqttClient.state());
      Serial.println(" (Coba lagi dalam 3 detik...)");
      
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("MQTT GAGAL! rc=");
      lcd.print(mqttClient.state());
      delay(3000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n==================================================");
  Serial.println("  RANOVA Smart Plug - EMQX Cloud MQTT Tester      ");
  Serial.println("==================================================");

  // Inisialisasi LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("RANOVA MQTT Test");
  lcd.setCursor(0, 1);
  lcd.print("Inisialisasi...");
  delay(1000);

  // Koneksi WiFi
  connectWiFi();

  // Konfigurasi TLS Client & MQTT
  netClient.setInsecure(); // Mengabaikan validasi root CA ketat agar handshake TLS langsung sukses
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512); // Perbesar buffer untuk JSON payload
}

void loop() {
  // Cek koneksi WiFi
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    return;
  }

  // Cek koneksi MQTT
  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  // Publish telemetri simulasi tiap 3 detik
  unsigned long now = millis();
  if (now - lastPublishTime >= PUBLISH_INTERVAL) {
    lastPublishTime = now;
    packetCount++;

    // Buat data payload JSON
    StaticJsonDocument<256> doc;
    doc["device"]      = "RANOVA-001";
    doc["packet_id"]   = packetCount;
    doc["voltage"]     = 223.4 + (random(-10, 10) * 0.1);
    doc["current"]     = 0.45;
    doc["power"]       = 100.5;
    doc["energy"]      = 0.0542;
    doc["temperature"] = 28.5 + (random(-5, 5) * 0.1);
    doc["humidity"]    = 65.0;
    doc["uptime_s"]    = now / 1000;

    char jsonBuf[256];
    size_t len = serializeJson(doc, jsonBuf);

    if (mqttClient.publish(TOPIC_TELEMETRY, jsonBuf)) {
      Serial.print("[MQTT TX] Paket #");
      Serial.print(packetCount);
      Serial.print(" berhasil terkirim ke ");
      Serial.println(TOPIC_TELEMETRY);
      Serial.print("          Payload: ");
      Serial.println(jsonBuf);

      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("TX #");
      lcd.print(packetCount);
      lcd.print(" -> EMQX OK");
      lcd.setCursor(0, 1);
      lcd.print("220V | 100W | 28C");
    } else {
      Serial.println("[MQTT TX] GAGAL mengirim paket!");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("TX FAILED!");
    }
  }
}
