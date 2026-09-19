/*
 * ============================================================
 * RANOVA Smart Plug - TES 1C: Relay 4-Channel (Channel 1 - Sisi DC)
 * ============================================================
 * Tujuan  : Memastikan Relay Channel 1 bekerja (bunyi klik & LED relay nyala)
 *           dikendalikan oleh Tombol Fisik 1 dan lampu LED Ring.
 *
 * Wiring Relay ke ESP32:
 *   VCC  --> ESP32 VIN     (Wajib 5V)
 *   GND  --> ESP32 GND     (Ground)
 *   IN1  --> ESP32 GPIO 23 (Active-LOW: LOW = Relay ON, HIGH = Relay OFF)
 *
 * Wiring Tombol 1 & LED Ring 1 (Tetap seperti Tes 1B):
 *   Tombol NO  --> ESP32 GPIO 27
 *   Tombol COM --> ESP32 GND
 *   LED Ring + --> ESP32 GPIO 32
 *   LED Ring - --> ESP32 GND
 * ============================================================
 */

const int PIN_BTN   = 27;  // Tombol Slot 1 (INPUT_PULLUP)
const int PIN_LED   = 32;  // LED Ring Slot 1 (OUTPUT)
const int PIN_RELAY = 23;  // Relay Channel 1 (OUTPUT, Active-LOW)

bool relayState = false;   // false = OFF, true = ON
bool lastButtonReading = HIGH;
unsigned long lastDebounceTime = 0;
const unsigned long debounceDelay = 50;

void setRelay(bool on) {
  // Modul Relay Optocoupler bekerja secara Active-LOW:
  // LOW  = Relay Aktif / ON (Lampu indikator relay menyala, bunyi KLIK)
  // HIGH = Relay Mati  / OFF (Lampu indikator relay mati, bunyi KLIK)
  digitalWrite(PIN_RELAY, on ? LOW : HIGH);
  digitalWrite(PIN_LED, on ? HIGH : LOW); // LED Ring menyala jika Relay ON
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n=======================================================");
  Serial.println("  RANOVA - TES 1C: INTEGRASI TOMBOL + LED + RELAY 1    ");
  Serial.println("=======================================================");

  pinMode(PIN_BTN, INPUT_PULLUP);
  pinMode(PIN_LED, OUTPUT);
  pinMode(PIN_RELAY, OUTPUT);

  // Pastikan Relay dan LED mati saat ESP32 baru menyala
  setRelay(false);
  Serial.println("Kondisi awal: Relay 1 OFF, LED Ring OFF.");

  // Tes awal otomatis: Nyalakan relay selama 1 detik lalu matikan
  Serial.println("\n[TES OTOMATIS] Menyalakan Relay 1 selama 1 detik...");
  setRelay(true);
  delay(1000);
  setRelay(false);
  Serial.println("[TES OTOMATIS] Selesai. Relay kembali OFF.\n");

  Serial.println("-------------------------------------------------------");
  Serial.println("SEKARANG: Tekan Tombol 1 untuk MENGHIDUPKAN/MEMATIKAN relay.");
  Serial.println(">> Tekan ke-1: Relay ON (KLIK!) + LED Ring NYALA");
  Serial.println(">> Tekan ke-2: Relay OFF (KLIK!) + LED Ring MATI");
  Serial.println("-------------------------------------------------------\n");
}

void loop() {
  bool reading = digitalRead(PIN_BTN);

  if (reading != lastButtonReading) {
    lastDebounceTime = millis();
  }

  if ((millis() - lastDebounceTime) > debounceDelay) {
    // Jika tombol baru saja ditekan (transisi dari HIGH ke LOW)
    static bool buttonState = HIGH;
    if (reading != buttonState) {
      buttonState = reading;

      if (buttonState == LOW) {
        // Balik status relay (Toggle)
        relayState = !relayState;
        setRelay(relayState);

        if (relayState) {
          Serial.println(">>> [KLIK!] RELAY 1 AKTIF (ON)  | LED Ring: NYALA");
        } else {
          Serial.println("--- [KLIK!] RELAY 1 MATI  (OFF) | LED Ring: MATI");
        }
      }
    }
  }

  lastButtonReading = reading;
}