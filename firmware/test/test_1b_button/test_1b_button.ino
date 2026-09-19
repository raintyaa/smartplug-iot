/*
 * ============================================================
 * RANOVA Smart Plug - TES 1B: Tombol Metal 16mm + LED Ring (Slot 1)
 * ============================================================
 * Tujuan  : Memastikan tombol terbaca dan lampu LED ring menyala
 * Komponen: ESP32 DevKit V1 + 1 unit Metal Push Button 16mm
 *
 * Wiring 5 Kaki Tombol Slot 1:
 *   NO        --> ESP32 GPIO 27  (Sinyal tombol)
 *   C / COM   --> ESP32 GND      (Ground saklar)
 *   + (LED+)  --> ESP32 GPIO 32  (Kontrol lampu LED ring)
 *   - (LED-)  --> ESP32 GND      (Ground LED ring)
 *   NC        --> KOSONG         (Tidak dipakai)
 * ============================================================
 */

const int PIN_BTN = 27;  // Tombol Slot 1 (INPUT_PULLUP)
const int PIN_LED = 32;  // LED Ring Slot 1 (OUTPUT)

bool lastButtonState = HIGH;

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n=============================================");
  Serial.println("  RANOVA - TES 1B: TOMBOL & LED RING SLOT 1  ");
  Serial.println("=============================================");

  // Inisialisasi pin
  pinMode(PIN_BTN, INPUT_PULLUP);
  pinMode(PIN_LED, OUTPUT);

  // Tes awal LED Ring: Kedipkan 3 kali untuk cek lampu sehat
  Serial.println("Mengetes lampu LED ring (kedip 3x)...");
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_LED, HIGH);
    delay(200);
    digitalWrite(PIN_LED, LOW);
    delay(200);
  }

  Serial.println("Siap! Silakan tekan tombol fisik Slot 1 sekarang.");
  Serial.println(">> Saat ditekan: LED Ring menyala & muncul log di sini.");
  Serial.println(">> Saat dilepas: LED Ring mati.\n");
}

void loop() {
  bool currentButtonState = digitalRead(PIN_BTN);

  // Deteksi perubahan status tombol
  if (currentButtonState != lastButtonState) {
    delay(20); // Debounce sederhana
    currentButtonState = digitalRead(PIN_BTN);

    if (currentButtonState == LOW) {
      // Tombol sedang ditekan (Active-LOW)
      digitalWrite(PIN_LED, HIGH);
      Serial.println(">>> [TOMBOL 1 DITEKAN]  -> LED Ring: NYALA (HIGH)");
    } else {
      // Tombol dilepas
      digitalWrite(PIN_LED, LOW);
      Serial.println("--- [TOMBOL 1 DILEPAS]  -> LED Ring: MATI (LOW)");
    }

    lastButtonState = currentButtonState;
  }
}