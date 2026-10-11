# ⚡ Sistem Smart Plug Berbasis ESP32 Terintegrasi QRIS Payment Gateway dan Monitoring Daya Listrik

Proyek akhir mata kuliah Internet of Things (Semester 5) & Ubiquitous Computing untuk otomatisasi dan monetisasi stop kontak listrik menggunakan pembayaran QRIS berbasis mikrokontroler ESP32, sensor daya listrik, sensor suhu keselamatan, protokol komunikasi terstandar, dan web dashboard monitoring realtime.

---

## 📌 Gambaran Umum Sistem & Alur Interaksi Pengguna

Sistem dirancang dengan alur interaksi fisik ke digital (*Physical-to-Digital Seamless Flow*):
1. **Pilih Slot (Tombol Fisik):** Pengguna menekan salah satu dari 3 tombol metal di alat (Slot 1, 2, atau 3).
2. **Indikasi Lokal:** ESP32 mendeteksi tombol yang ditekan:
   * Lampu LED ring pada tombol yang dipilih mulai **berkedip (*blinking*)**.
   * Layar LCD 16x2 menampilkan teks: `"SLOT X TERPILIH"` dan `"SILAKAN SCAN QR"`.
   * ESP32 mencatat status sementara di Firebase (`active_selection: { slot: X, status: "WAITING_PAYMENT" }`).
3. **Scan QR Code:** Pengguna melakukan scan stiker QR code pada box alat menggunakan kamera HP.
4. **Halaman Pembayaran Terbuka:** Halaman Web Pembayaran (`pay.html`) otomatis membaca slot yang sedang dipilih pengguna secara *realtime*.
5. **Input Nominal Mandiri:** Pengguna mengetikkan nominal penggunaan yang diinginkan (misal: Rp 2.000, Rp 5.000, dll.). Sistem otomatis mengonversi nominal tersebut menjadi durasi waktu pemakaian (proporsional per Rp 1.000 = 15 menit).
6. **Konfirmasi Bayar:** Pengguna menekan tombol "Bayar Sekarang".
7. **Aktivasi Otomatis (Relay & LED):**
   * Webhook Mayar otomatis mengupdate status Firebase ke `ACTIVE`.
   * ESP32 merespons instan: Relay stop kontak terpilih otomatis **ON** dan lampu LED ring tombol berubah dari berkedip menjadi **menyala solid**.
   * Layar LCD 16x2 menampilkan konfirmasi visual: `"BAYAR BERHASIL"` dan `"SLOT X AKTIF"` selama 4 detik, lalu otomatis kembali ke mode **Standby** (`"RANOVA PLUG / Tekan Tombol..."`) agar pengguna lain dapat langsung menyewa slot berikutnya tanpa terganggu.
   * Sisa durasi waktu pemakaian (*countdown timer*) dan telemetri daya (*Watt, kWh, Rupiah*) untuk ketiga stop kontak dipantau secara terpusat melalui **Web Dashboard Realtime**.
8. **Proteksi & Monitoring:** Selama aktif, sensor PZEM-004T memantau konsumsi daya listrik (Watt/kWh) dan sensor DHT22 memantau suhu internal box demi keselamatan (*closed-loop safety*).

---

## 💳 Strategi Integrasi Pembayaran QRIS Dinamis

Sistem menggunakan **Mayar Payment Gateway (Mayar.id)** sebagai infrastruktur utama pemrosesan transaksi QRIS otomatis secara *event-driven*:

### 1. Payment Gateway Resmi (Mayar QRIS Dinamis) ⭐
* **Alur:** 
  1. Pengguna menekan tombol fisik slot yang ingin disewa pada stasiun pengisian daya.
  2. Pengguna memindai stiker QRIS resmi Mayar pada bodi alat dengan aplikasi m-banking atau e-wallet (BCA Mobile, Mandiri Livin, GoPay, OVO, Dana, ShopeePay, dll.).
  3. Pengguna menginput dan membayar nominal sewa yang diinginkan (kelipatan Rp 1.000 = 15 menit).
  4. Server Mayar memverifikasi pembayaran sukses dan memicu callback **HTTP POST Webhook** ke Vercel Serverless Function (`api/webhook.js`).
  5. Vercel menghitung durasi waktu sewa secara proporsional dan mengupdate status slot di **Firebase Realtime Database** menjadi `ACTIVE`.
  6. ESP32 mendeteksi perubahan status secara realtime: Relay stop kontak ON, LED ring menyala solid, dan beban listrik menyala otomatis.
* **Status:** **Production Active & Terintegrasi Penuh** (Telah teruji sukses end-to-end dengan transaksi riil dan aktivasi beban nyata charger laptop).

### 2. Self-Hosted Interactive Web Payment Portal (HTTPS REST API Fallback)
* **Alur:**
  1. Pengguna menekan tombol fisik slot pada box alat.
  2. Pengguna scan QR code stiker alat dengan kamera HP -> Web Portal Pembayaran RANOVA (`pay.html`) terbuka.
  3. Web secara cerdas membaca slot yang dipilih dari Firebase, dan pengguna memasukkan nominal durasi yang diinginkan.
  4. Pengguna klik "Konfirmasi Pembayaran" -> Web portal mengirimkan `HTTPS REST API` ke Firebase Realtime Database.
  5. ESP32 merespons perubahan data: Relay aktif, LED menyala solid, timer berjalan.
* **Fungsi:** Sebagai portal alternatif interaktif untuk pengujian cepat, simulasi offline, dan demonstrasi tanpa ketergantungan pihak ketiga.

---

## 🌐 Pemetaan Protokol Komunikasi (Ubiquitous Computing & IoT)

| Lapisan (Layer) | Protokol | Implementasi pada Sistem |
|---|---|---|
| **Hardware Bus** | **UART (Serial2)** | Komunikasi serial data PZEM-004T (GPIO 16/17, 9600 bps) |
| | **I2C (Wire)** | Komunikasi display LCD 16x2 (GPIO 21 SDA, GPIO 22 SCL) |
| | **1-Wire Digital** | Komunikasi pembacaan sensor suhu DHT22 (GPIO 4) |
| **Wireless LAN** | **IEEE 802.11 b/g/n** | Koneksi nirkabel Wi-Fi 2.4 GHz ESP32 ke Internet |
| **Cloud & API** | **HTTPS (TLS/SSL)** | Enkripsi data komunikasi web pembayaran dan dashboard |
| | **REST API & Webhook**| Transmisi status pembayaran ke backend / Firebase |
| | **WebSocket / MQTT** | Sinkronisasi data realtime dua arah ke ESP32 dan Dashboard |

---

## 🛠️ Spesifikasi Komponen (Final Bill of Materials & Enclosure)

### Hardware Utama
* **Mikrokontroler:** ESP32 DevKit V1 30-Pin (WiFi 2.4 GHz)
* **Aktuator:** Modul Relay 4-Channel 5V Optocoupler (Active LOW)
* **Sensor 1 (Daya Listrik):** **PZEM-004T V3.0 / V4.0 100A** dengan Koil CT (Pin Header 4-Pin, Modbus RTU via UART)
  * Mengukur: Tegangan (V), Arus (A), Daya (W), Energi (kWh), Frekuensi (Hz), Power Factor
  * Fungsi: Monitoring konsumsi daya, analisis biaya listrik, dan deteksi idle (auto-shutdown jika arus ≈ 0A selama 5 menit)
* **Sensor 2 (Suhu & Kelembaban):** **DHT22 Modul 3-Pin** (Built-in Pull-Up Resistor)
  * Mengukur: Suhu (°C) dan Kelembaban (%)
  * Fungsi: Proteksi keselamatan terhadap overheating di dalam casing (emergency shutdown jika suhu > 60°C)
* **Antarmuka Tombol & Indikator:** 3 unit **Metal Push Button 16mm Self-Reset (Momentary) 5V LED Ring** (Tombol fisik dan lampu status terintegrasi menjadi satu unit per stop kontak)
* **Display Lokal:** Modul LCD 16x2 Karakter dengan I2C Backpack tersolder (PCFL8574)
* **Catu Daya DC:** Switching Power Supply 5V (3A - 5A)

### Kelistrikan AC 220V & Pengaman
* **Stop Kontak:** 3 unit **Broco Alleg C154-11 Inbow Flush-Mount (80 x 80 mm)** dengan pin Arde/Ground
* **Steker Listrik:** 1 unit **Steker Arde Bulat Dutron DV-SAB-01 (10A/16A 250V SNI)**
* **Kabel Daya AC:** **NYMHY 3x1.5mm²** (3-Core Serabut: Fasa/Coklat, Netral/Biru, Ground/Kuning-Hijau)
* **Pengaman:** Fuse Holder Panel Mount + Sekring Kaca AC 5A/10A
* **Distribusi:** Terminal Block Sambungan Kabel AC / Wago Connector
* **Cable Gland:** PG13.5 (Pengunci kabel masuk pada sisi bawah box)

### Spesifikasi Casing & Desain Enclosure
* **Tipe Casing:** Kayu Triplek / Plywood Tebal 9mm – 12mm (Finishing Amplas, Cat/Pernis, atau Lapisan HPL)
* **Dimensi Box:** **~220 x 300 x 100 mm** (Orientasi **Vertikal**)
* **Desain Ergonomi & Maintenance:**
  * **Panel Depan:** Dinding kayu rata untuk pemasangan LCD 16x2, 3x tombol metal 16mm, 3x stop kontak Broco Inbow, dan stiker QRIS.
  * **Panel Belakang (Engsel/Pintu):** Pintu belakang berengsel atau sekrup lepas-pasang untuk akses teknisi membuka/maintenance sirkuit ESP32, kabel 220V, dan sekring tanpa merusak panel depan.
* **Layout Panel Depan (Vertikal):**
  * **Kolom Kiri:** LCD 16x2 I2C (atas) ➔ 3x Tombol Metal 16mm (tengah) ➔ Stiker QRIS (bawah).
  * **Kolom Kanan:** 3x Stop Kontak Broco 80x80mm tersusun vertikal dari Slot 1, Slot 2, hingga Slot 3.
* **Metode Mounting Komponen Internal:**
  * **Relay 4-Ch:** Spacer Kuningan M3 x 10mm (4 lubang bor di dasar box) untuk isolasi jalur tembaga PCB.
  * **ESP32 DevKit:** Mini Breadboard (170 titik, 47x35mm) dengan perekat stiker busa bawaan / Expansion Shield.
  * **Power Supply 5V:** Spacer M3 atau Double Tape 3M VHB.
  * **Sensor PZEM-004T & DHT22:** Double Tape 3M VHB (DHT22 ditempatkan dekat lubang ventilasi).
  * **LCD 16x2:** Cutout panel 71x26mm + 4x baut M3.
  * **Tombol Metal:** 3x lubang bor Ø16mm.
  * **Stop Kontak Broco:** 3x lubang bor/cutout bulat Ø60mm untuk body inbow.

---

## 🔌 Pemetaan Pin GPIO ESP32 & Panduan Wiring Fisik (Pin-to-Pin)

> 📖 **Panduan Lengkap & Terperinci:** Lihat dokumen khusus [**`docs/WIRING_GUIDE.md`**](docs/WIRING_GUIDE.md) untuk arsitektur Common Ground (WAGO), trik kabel estafet tombol metal, kelistrikan AC 220V, dan daftar komponen tambahan.

### Tabel Pin ESP32 DevKit V1 (30-Pin)
| Komponen | Pin Modul | Pin ESP32 | Keterangan |
|---|---|---|---|
| **LCD 16x2 I2C** | SDA | `GPIO 21` | Jalur Data I2C |
| | SCL | `GPIO 22` | Jalur Clock I2C |
| **Relay 4-Channel** | IN1 (Slot 1) | `GPIO 23` | Kontrol Relay Stop Kontak 1 (Active-LOW) |
| | IN2 (Slot 2) | `GPIO 19` | Kontrol Relay Stop Kontak 2 (Active-LOW) |
| | IN3 (Slot 3) | `GPIO 18` | Kontrol Relay Stop Kontak 3 (Active-LOW) |
| | IN4 (Spare) | `GPIO 26` | Cadangan Channel 4 (Active-LOW) |
| **Sensor PZEM-004T** | RX | `GPIO 17` (TX2) | Komunikasi Serial UART (PZEM RX <- ESP32 TX2) |
| | TX | `GPIO 16` (RX2) | Komunikasi Serial UART (PZEM TX -> ESP32 RX2) |
| **Sensor DHT22** | DATA / OUT | `GPIO 4` | 1-Wire Digital Suhu & Kelembaban |
| **Tombol Metal 16mm** | Tombol 1 (NO) | `GPIO 27` | Input Tombol Slot 1 (Internal Pull-Up) |
| | Tombol 2 (NO) | `GPIO 14` | Input Tombol Slot 2 (Internal Pull-Up) |
| | Tombol 3 (NO) | `GPIO 12` | Input Tombol Slot 3 (Internal Pull-Up) |
| **LED Ring Tombol** | LED Ring 1 (+) | `GPIO 32` | Indikator Lampu Slot 1 Aktif |
| | LED Ring 2 (+) | `GPIO 33` | Indikator Lampu Slot 2 Aktif |
| | LED Ring 3 (+) | `GPIO 25` | Indikator Lampu Slot 3 Aktif |

*Total Pin Terpakai: 15 pin dari 25+ pin GPIO tersedia.*

---

### 📋 Detail Wiring Setiap Komponen Fisik

#### 1. Tombol Metal 16mm dengan LED Ring (5 Kaki / Terminal per Tombol)
Setiap tombol memiliki 5 kaki di bagian belakang: `+` (Anoda LED), `-` (Katoda LED), `C / COM` (Common), `NO` (Normally Open), dan `NC` (Normally Closed, kosong).
* **Tombol Slot 1:**
  * `NO` ➔ **ESP32 GPIO 27**
  * `C / COM` ➔ **GND Bersama**
  * `+ (LED+)` ➔ **ESP32 GPIO 32**
  * `- (LED-)` ➔ **GND Bersama**
  * `NC` ➔ *Kosong*
* **Tombol Slot 2:**
  * `NO` ➔ **ESP32 GPIO 14**
  * `C / COM` ➔ **GND Bersama**
  * `+ (LED+)` ➔ **ESP32 GPIO 33**
  * `- (LED-)` ➔ **GND Bersama**
  * `NC` ➔ *Kosong*
* **Tombol Slot 3:**
  * `NO` ➔ **ESP32 GPIO 12**
  * `C / COM` ➔ **GND Bersama**
  * `+ (LED+)` ➔ **ESP32 GPIO 25**
  * `- (LED-)` ➔ **GND Bersama**
  * `NC` ➔ *Kosong*

#### 2. Modul LCD 16x2 I2C (4 Pin)
* `GND` ➔ **ESP32 GND**
* `VCC` ➔ **PSU 5V / VIN**
* `SDA` ➔ **ESP32 GPIO 21**
* `SCL` ➔ **ESP32 GPIO 22**

#### 3. Modul DHT22 Suhu & Kelembaban (3 Pin)
* `VCC (+)` ➔ **ESP32 3.3V**
* `GND (-)` ➔ **ESP32 GND**
* `DATA / OUT` ➔ **ESP32 GPIO 4**

#### 4. Sensor Daya PZEM-004T V3.0 (Logika DC & AC 220V)
* **Sisi Logika DC (Pin Header 4-Pin):**
  * `5V` ➔ **PSU 5V** (wajib 5V stabil untuk optocoupler internal)
  * `GND` ➔ **ESP32 GND**
  * `TX` ➔ **ESP32 GPIO 16 (RX2)**
  * `RX` ➔ **ESP32 GPIO 17 (TX2)**
* **Sisi AC 220V:**
  * `L & N` ➔ Paralel ke Fasa & Netral PLN AC 220V
  * `CT Port` ➔ 2 kabel dari koil donat CT (kabel Fasa PLN dimasukkan menembus lubang koil)

#### 5. Modul Relay 4-Channel 5V Optocoupler (Active-LOW)
* **Sisi Kontrol DC:**
  * `VCC` ➔ **PSU 5V**
  * `GND` ➔ **GND Bersama (PSU & ESP32)**
  * `IN1` ➔ **ESP32 GPIO 23** (Slot 1)
  * `IN2` ➔ **ESP32 GPIO 19** (Slot 2)
  * `IN3` ➔ **ESP32 GPIO 18** (Slot 3)
  * `IN4` ➔ **ESP32 GPIO 26** (Spare / Cadangan)
* **Sisi Terminal AC 220V (Pemutus Beban Listrik):**
  * `COM` Channel 1, 2, 3 ➔ Kabel Fasa (Coklat) dari PLN (setelah sekring & CT PZEM)
  * `NO` Channel 1 ➔ Lubang Fasa Stop Kontak Broco Slot 1
  * `NO` Channel 2 ➔ Lubang Fasa Stop Kontak Broco Slot 2
  * `NO` Channel 3 ➔ Lubang Fasa Stop Kontak Broco Slot 3
  * `NC` ➔ *Kosongkan*



## 🔒 Sistem Proteksi Otomatis (Closed-Loop Safety)

| Skenario | Pemicu | Sensor Terlibat |
|---|---|---|
| **Timer Habis** | Durasi waktu yang dibeli pengguna sudah berakhir | Logika timer ESP32 (`millis()`) |
| **Auto-Shutdown Idle** | Tidak ada perangkat dicolokkan selama 5 menit (arus ≈ 0A) | **Sensor PZEM-004T** |
| **Emergency Shutdown Suhu** | Suhu internal casing melebihi 60°C | **Sensor DHT22** |

---

## 💰 Analisis Biaya & Keuntungan (Fitur Dashboard)

Sensor PZEM-004T mengukur kWh secara akumulatif, sehingga sistem dapat menghitung:
* **Biaya Listrik PLN:** `kWh terpakai x Rp 1.444/kWh`
* **Total Pendapatan:** Jumlah seluruh transaksi masuk
* **Laba Bersih:** `Total Pendapatan - Total Biaya Listrik`

---

## 📋 Roadmap Progres (14 Minggu)

- [x] **Fase 1: Riset, Desain Sistem & Pemetaan Protokol (SELESAI)**
  - [x] Finalisasi arsitektur sistem & 3 opsi integrasi pembayaran QRIS.
  - [x] Finalisasi spesifikasi hardware: 2 sensor (PZEM-004T + DHT22), relay 4-ch, 3 tombol metal 16mm LED ring.
  - [x] Pemetaan 6 protokol komunikasi untuk MK IoT & Ubiquitous Computing.
  - [x] Landing page portofolio & registrasi merchant aktif (`https://raintyaa.github.io/smartplug-iot/`).
- [x] **Fase 2: Setup Cloud Database (Firebase), Simulasi Wokwi & Integrasi Webhook (SELESAI)**
  - [x] Setup project Firebase Realtime Database & skema data JSON.
  - [x] Integrasi Webhook Mayar QRIS -> Vercel Serverless Function -> Firebase (teruji sukses end-to-end).
  - [x] Pembuatan virtual prototype firmware di simulator Wokwi & PlatformIO VS Code (ESP32 + WiFi + LCD + Relay 4-ch + DHT22 + PZEM-004T).
  - [x] Finalisasi UX LCD: notifikasi transaksi sukses transien (4 detik) lalu kembali Standby, countdown & telemetri multi-slot terpusat ke Web Dashboard.
  - [x] Pembelian komponen hardware online sesuai BOM & Enclosure (WAGO PCT-215, NYA-F 1.5mm², Steker Dutron, Stop Kontak Broco, Relay 4-ch, Tombol Metal 16mm).
- [x] **Fase 3: Pengujian Hardware Bertahap (Incremental Bring-up) — SELESAI 100%**
  - [x] **Tes 1A:** I2C Scanner & Kontrol Backlight LCD 16x2 terverifikasi pada alamat `0x27`.
  - [x] **Tes 1B:** Tombol Metal 16mm & LED Ring Slot 1 (`GPIO 27` & `GPIO 32`) teruji sukses responsif 100%.
  - [x] **Tes 1C:** Modul Relay 4-Channel (Channel 1 `GPIO 23` Active-LOW) terintegrasi dengan Tombol 1 & LED Ring.
  - [x] Dokumentasi solusi isolasi pin `NC` tombol & arsitektur distribusi daya Common Ground (WAGO Connector).
  - [x] **Tahap 2:** Uji End-to-End Single-Slot Fisik ke Cloud (ESP32 + WiFi + Firebase + Webhook QRIS Mayar) — **SUKSES 100%**!
  - [x] **Tahap 3:** Uji Kelistrikan AC 220V pada Stop Kontak Broco Slot 1 (Beban nyata: charger laptop) — **SUKSES 100%**!
  - [x] **Tahap 4:** Pengujian Sensor Suhu DHT22 (GPIO 4) & Sensor Daya PZEM-004T (UART GPIO 16/17) [SELESAI 100%].
  - [x] **Tahap 4B:** Uji Terpadu 1 Slot + PZEM + DHT22 + LCD 16x2 + Relay + Tombol Metal + Mayar Webhook (Tes 3) [SELESAI 100%].
  - [x] **Tahap 5:** Replikasi pengujian untuk 3 Slot (3 Tombol, 3 Relay, 3 Stop Kontak via WAGO Connector) — **SUKSES TERUJI SECARA SIMULTAN**!
- [x] **Fase 4: Integrasi Full System ke Cloud, Payment Trigger & Safety — SELESAI 100%**
  - [x] ESP32 membaca trigger status ON/OFF dan durasi dari Firebase secara realtime (Dual-Node sync & Batch Polling `/slots.json`).
  - [x] Implementasi logika *Early Stop* instan (0 ms delay) via **Hardware Interrupts (`attachInterrupt` mode `FALLING`)** & *Falling Edge Detection*.
  - [x] Implementasi sistem proteksi keselamatan termal **Thermal Hysteresis** (Emergency Stop $T \ge 60^\circ\text{C}$ & Auto-Recovery $T < 50^\circ\text{C}$).
  - [x] Optimasi tampilan LCD 16x2: Telemetri daya PZEM-004T bersih ($V, A, W$) saat slot aktif dan live cooling alert saat overheat.
- [x] **Fase 5: Pembuatan Realtime Hardware Web Dashboard (Laravel Operator) — SELESAI 100%**
  - [x] Web Dashboard Operator Laravel (`ranova-dashboard`) dengan MySQL + Firebase RTDB.
  - [x] UI Realtime Telemetri Stop Kontak, Sensor PZEM & Sensor DHT22.
  - [x] Panel Log Transaksi, Countdown Timer, Kontrol Manual Stop, Emergency Cutoff, dan Laporan Laba Bersih.
  - [x] Sinkronisasi Dynamic Rental Pricing ke Firebase dan Webhook Vercel.
- [ ] **Fase 6: Fabrikasi Casing & Final Assembly (SEDANG BERJALAN)**
  - [ ] Perakitan komponen ke dalam casing enclosure stasiun pengisian daya.
  - [ ] Finishing wiring jalur AC 220V + sekring pengaman 5A/10A.
  - [ ] Full system stress test & demonstrasi akhir.
- [ ] **Fase 7: Dokumentasi Akhir & Pameran Dies Natalis**
  - [ ] Penyusunan laporan akhir untuk MK IoT & Ubiquitous Computing.
  - [ ] Persiapan materi presentasi & demo stan pameran.
