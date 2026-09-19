/*
 * ============================================================
 * RANOVA Smart Plug - TES 1A: LCD 16x2 I2C
 * ============================================================
 * Tujuan  : Memastikan LCD terhubung dan menampilkan teks dengan benar
 * Komponen: ESP32 DevKit V1 + Modul LCD 16x2 I2C (PCF8574)
 *
 * Wiring:
 *   LCD VCC  --> ESP32 VIN (atau 5V dari PSU)
 *   LCD GND  --> ESP32 GND
 *   LCD SDA  --> ESP32 GPIO 21
 *   LCD SCL  --> ESP32 GPIO 22
 *
 * Library yang dibutuhkan (install di Arduino IDE):
 *   - LiquidCrystal I2C by Frank de Brabander
 * ============================================================
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ============================================================
// Jika LCD tidak muncul teks, coba ganti 0x27 dengan 0x3F
// Gunakan fitur I2C Scanner di bawah untuk menemukan alamatnya
// ============================================================
#define LCD_ADDRESS 0x27
#define LCD_COLS    16
#define LCD_ROWS    2

LiquidCrystal_I2C lcd(LCD_ADDRESS, LCD_COLS, LCD_ROWS);

// ============================================================
// I2C Scanner: scan semua alamat I2C yang terhubung
// Berguna jika LCD tidak muncul (untuk cek alamat yang benar)
// ============================================================
void scanI2C() {
  Serial.println("=== I2C Scanner ===");
  int deviceCount = 0;

  for (byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    byte error = Wire.endTransmission();

    if (error == 0) {
      Serial.print("Perangkat I2C ditemukan di alamat: 0x");
      if (addr < 16) Serial.print("0");
      Serial.println(addr, HEX);
      deviceCount++;
    }
  }

  if (deviceCount == 0) {
    Serial.println("Tidak ada perangkat I2C ditemukan!");
    Serial.println(">> Periksa kabel SDA (GPIO21) dan SCL (GPIO22)");
  } else {
    Serial.print("Total perangkat ditemukan: ");
    Serial.println(deviceCount);
  }
  Serial.println("===================\n");
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n=============================");
  Serial.println("  RANOVA - TES 1A: LCD I2C");
  Serial.println("=============================");

  // Init I2C bus
  Wire.begin(21, 22); // SDA=GPIO21, SCL=GPIO22

  // Scan dulu untuk debug
  scanI2C();

  // Init LCD
  Serial.println("Menginisialisasi LCD...");
  lcd.init();
  lcd.backlight(); // Nyalakan lampu belakang

  // --- Tampilan Tes 1: Pesan Selamat Datang ---
  lcd.setCursor(0, 0);
  lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1);
  lcd.print("  LCD OK!       ");

  Serial.println("LCD berhasil diinisialisasi!");
  Serial.println(">> Pastikan teks tampil di LCD sekarang.");
  Serial.println(">> Jika layar gelap/kosong, putar trimpot biru di modul I2C.");
  Serial.println();

  delay(2500);

  // --- Tampilan Tes 2: Uji Semua Posisi Karakter ---
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("0123456789ABCDEF"); // 16 karakter baris 1
  lcd.setCursor(0, 1);
  lcd.print("GHIJKLMNOPQRSTUV"); // 16 karakter baris 2

  Serial.println("Tampilan tes karakter:");
  Serial.println("Baris 1: [0123456789ABCDEF]");
  Serial.println("Baris 2: [GHIJKLMNOPQRSTUV]");
  Serial.println(">> Pastikan semua 32 karakter tampil jelas dan tidak ada yang hilang.");
  Serial.println();

  delay(2500);

  // --- Tampilan Tes 3: Tampilan Standby Final ---
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("  RANOVA PLUG   ");
  lcd.setCursor(0, 1);
  lcd.print(" Tekan Tombol...");

  Serial.println("Tampilan final standby aktif.");
  Serial.println(">> TES 1A SELESAI! LCD berfungsi dengan baik.");
  Serial.println(">> Lanjut ke Tes 1B (Tombol + LED Ring Slot 1).");
}

void loop() {
  // Loop kosong - LCD menampilkan teks standby secara permanen
  // Tidak ada yang dijalankan di sini
}