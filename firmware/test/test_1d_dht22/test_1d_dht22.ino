// ============================================================
// RANOVA Smart Plug - Uji Sensor Suhu & Kelembaban DHT22 (Tes 1D)
// ============================================================
// Tujuan:
// 1. Membaca nilai suhu (°C) dan kelembaban udara (%) dari sensor DHT22.
// 2. Menampilkan pembacaan berkala ke Serial Monitor (115200 baud).
// 3. Menampilkan pembacaan langsung di layar LCD 16x2 I2C.
// 4. Menguji logika deteksi bahaya overheat (> 60.0°C).
// ============================================================

#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>

// --- KONFIGURASI PIN ---
#define DHTPIN 4           // Pin DATA DHT22 terhubung ke GPIO 4
#define DHTTYPE DHT22      // Tipe sensor: DHT22 (AM2302)

#define LCD_ADDR 0x27      // Alamat I2C LCD (0x27)
#define LCD_COLS 16
#define LCD_ROWS 2

#define OVERHEAT_LIMIT 60.0 // Ambang batas suhu kritis (°C)

// Inisialisasi Objek
DHT dht(DHTPIN, DHTTYPE);
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);

unsigned long lastReadTime = 0;
const unsigned long READ_INTERVAL = 2000; // Baca setiap 2 detik (sesuai spesifikasi DHT22)

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n==========================================");
  Serial.println("  RANOVA SMART PLUG - TES SENSOR DHT22    ");
  Serial.println("==========================================");

  // Inisialisasi LCD
  Wire.begin(21, 22); // SDA=21, SCL=22
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("RANOVA IOT PLUG");
  lcd.setCursor(0, 1);
  lcd.print("Uji Sensor DHT22");

  // Inisialisasi DHT22
  Serial.println("[INFO] Memulai inisialisasi sensor DHT22 pada GPIO 4...");
  dht.begin();

  delay(2000); // Waktu pemanasan sensor awal
  lcd.clear();
}

void loop() {
  unsigned long currentMillis = millis();

  // Baca sensor setiap 2 detik (DHT22 butuh minimal interval 2 detik agar stabil)
  if (currentMillis - lastReadTime >= READ_INTERVAL) {
    lastReadTime = currentMillis;

    // Membaca kelembaban dan suhu (Celsius)
    float humidity = dht.readHumidity();
    float temperature = dht.readTemperature();

    // Cek apakah pembacaan sensor gagal
    if (isnan(humidity) || isnan(temperature)) {
      Serial.println("[ERROR] Gagal membaca data dari sensor DHT22!");
      Serial.println("        -> Periksa apakah kabel VCC, GND, dan DATA (GPIO 4) sudah terpasang kencang.");

      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("DHT22 Error!");
      lcd.setCursor(0, 1);
      lcd.print("Cek Kabel Sensor");
      return;
    }

    // --- 1. CETAK KE SERIAL MONITOR ---
    Serial.println("------------------------------------------");
    Serial.print("Suhu Lingkungan : ");
    Serial.print(temperature, 1);
    Serial.print(" °C");

    // Deteksi Peringatan Overheat
    if (temperature >= OVERHEAT_LIMIT) {
      Serial.print(" [! BAHAYA OVERHEAT !]");
    } else {
      Serial.print(" [Normal]");
    }
    Serial.println();

    Serial.print("Kelembaban Udara: ");
    Serial.print(humidity, 1);
    Serial.println(" %");

    // --- 2. TAMPILKAN KE LAYAR LCD 16x2 ---
    lcd.clear();

    if (temperature >= OVERHEAT_LIMIT) {
      // Tampilan jika suhu berbahaya
      lcd.setCursor(0, 0);
      lcd.print("! OVERHEAT DANGER");
      lcd.setCursor(0, 1);
      lcd.print("Suhu: ");
      lcd.print(temperature, 1);
      lcd.print((char)223); // Karakter derajat (°)
      lcd.print("C");
    } else {
      // Tampilan normal
      // Baris 1: Suhu
      lcd.setCursor(0, 0);
      lcd.print("Suhu  : ");
      lcd.print(temperature, 1);
      lcd.print((char)223); // Karakter derajat (°)
      lcd.print("C");

      // Baris 2: Kelembaban
      lcd.setCursor(0, 0);
      lcd.setCursor(0, 1);
      lcd.print("Lembab: ");
      lcd.print(humidity, 1);
      lcd.print("%  [OK]");
    }
  }
}
