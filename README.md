# ⚡ Sistem Smart Plug Berbasis ESP32 Terintegrasi QRIS Payment Gateway dan Monitoring Daya Listrik

Proyek akhir mata kuliah Internet of Things (Semester 5) & Ubiquitous Computing untuk otomatisasi dan monetisasi stop kontak listrik menggunakan pembayaran QRIS berbasis mikrokontroler ESP32, sensor daya listrik, sensor suhu keselamatan, protokol komunikasi terstandar, dan web dashboard monitoring realtime.

---

## 📌 Gambaran Umum Sistem

Sistem ini dirancang untuk menyediakan akses daya listrik berbayar mandiri (*self-service charging station*):
1. **Pengguna** memilih stop kontak dengan menekan tombol metal berlampu pada slot yang diinginkan.
2. **Pengguna** melakukan pembayaran melalui stiker QRIS pada panel alat (nominal menentukan durasi).
3. **Sistem Pembayaran** memvalidasi transaksi secara otomatis via HTTPS REST API & Webhook.
4. **Cloud Database (Firebase)** menerima status pembayaran dan mengaktifkan timer proporsional sesuai nominal bayar.
5. **ESP32** menyalakan relay stop kontak yang dipilih, menyalakan lampu LED ring pada tombol, dan mengukur beban daya listrik via sensor.
6. **Sensor PZEM-004T** memantau konsumsi daya (Watt/kWh) untuk analisis biaya dan deteksi perangkat idle.
7. **Sensor DHT22** memantau suhu internal casing untuk proteksi keselamatan terhadap overheating.
8. **Web Dashboard** menampilkan status realtime, konsumsi daya, suhu internal, serta histori transaksi.

---

## 💳 3 Strategi Integrasi Pembayaran QRIS

Sistem dirancang modular untuk mendukung 3 strategi pembayaran:

### 1. Payment Gateway Resmi Nasional (Midtrans / Mayar)
* **Alur:** Pengguna scan QRIS resmi -> Bayar via E-Wallet/M-Banking -> Server Gateway kirim Webhook HTTP POST otomatis ke Backend/Firebase -> ESP32 aktif.
* **Status:** Midtrans Sandbox aktif & siap; Pengajuan akun Production dalam proses review.

### 2. Self-Hosted Interactive Web Payment Portal & Mock Gateway (HTTPS REST API / Webhook) ⭐
* **Alur:** Pengguna scan QR code pada box fisik menggunakan kamera HP -> Halaman Web Pembayaran RANOVA terbuka di browser HP -> Pengguna memilih slot dan nominal -> Klik "Konfirmasi Bayar" -> Web App mengirim request HTTPS REST API (`POST/PATCH`) ke Cloud Endpoint/Firebase -> Sinkronisasi Realtime -> ESP32 menyalakan relay.
* **Kelebihan:** 100% interaktif nyata di HP penguji/dosen, handal, dan menerapkan protokol HTTPS, REST API, Webhook, serta WebSocket/MQTT secara nyata tanpa ketergantungan pihak ketiga.

### 3. DANA Bisnis QRIS Notification Forwarder
* **Alur:** Menggunakan stiker QRIS Nasional resmi dari akun DANA Bisnis -> Notifikasi push pembayaran masuk ke HP -> Aplikasi MacroDroid menangkap notifikasi dan mengirim HTTP POST ke Firebase -> ESP32 aktif.

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
* **Tipe Casing:** Box Panel Listrik ABS (IP65 Dustproof & Waterproof)
* **Dimensi Box:** **~220 x 300 x 100 mm** (Orientasi **Vertikal**)
* **Desain Ergonomi & Maintenance:**
  * **Sisi Belakang Box (Dinding Rata):** Difungsikan sebagai **Panel Depan (User-facing)** untuk pemasangan LCD, 3x tombol, 3x stop kontak Broco, dan stiker QRIS.
  * **Sisi Pintu Box (Berkunci):** Difungsikan sebagai **Panel Belakang (Maintenance access)** untuk teknisi/pengembang membuka akses debugging ESP32, fuse, dan wiring internal tanpa membongkar panel utama.
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

## 🔌 Pemetaan Pin GPIO ESP32

| Komponen | Pin Modul | Pin ESP32 | Keterangan |
|---|---|---|---|
| **LCD 16x2 I2C** | SDA | `GPIO 21` | Jalur Data I2C |
| | SCL | `GPIO 22` | Jalur Clock I2C |
| **Relay 4-Channel** | IN1 (Slot 1) | `GPIO 23` | Kontrol Relay Stop Kontak 1 |
| | IN2 (Slot 2) | `GPIO 19` | Kontrol Relay Stop Kontak 2 |
| | IN3 (Slot 3) | `GPIO 18` | Kontrol Relay Stop Kontak 3 |
| **Sensor PZEM-004T** | RX | `GPIO 17` (TX2) | Komunikasi Serial UART |
| | TX | `GPIO 16` (RX2) | Komunikasi Serial UART |
| **Sensor DHT22** | DATA | `GPIO 4` | Data Suhu & Kelembaban |
| **Tombol Metal 16mm** | Tombol 1 (NO) | `GPIO 27` | Input Tombol Slot 1 (Internal Pull-Up) |
| | Tombol 2 (NO) | `GPIO 14` | Input Tombol Slot 2 (Internal Pull-Up) |
| | Tombol 3 (NO) | `GPIO 12` | Input Tombol Slot 3 (Internal Pull-Up) |
| **LED Ring Tombol** | LED Ring 1 (+) | `GPIO 32` | Indikator Lampu Slot 1 Aktif |
| | LED Ring 2 (+) | `GPIO 33` | Indikator Lampu Slot 2 Aktif |
| | LED Ring 3 (+) | `GPIO 25` | Indikator Lampu Slot 3 Aktif |

**Total Pin Terpakai: 14 pin** dari 25+ pin GPIO tersedia.

---

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
- [ ] **Fase 2: Setup Cloud Database (Firebase), Simulasi Wokwi & Pengadaan Komponen**
  - [ ] Pembelian komponen hardware online sesuai BOM & Enclosure.
  - [ ] Setup project Firebase Realtime Database & skema data JSON.
  - [ ] Pembuatan virtual prototype firmware di simulator Wokwi (ESP32 + WiFi + LCD + Relay + DHT22).
  - [ ] Rancang interactive web payment portal (Opsi 2).
- [ ] **Fase 3: Perakitan & Pengujian Hardware di Breadboard**
  - [ ] Wiring & coding relay 3-channel + countdown timer `millis()`.
  - [ ] Integrasi display LCD 16x2 I2C & 3 tombol metal berlampu.
  - [ ] Kalibrasi pembacaan tegangan, arus, dan daya dari sensor PZEM-004T via UART.
  - [ ] Integrasi sensor DHT22 via 1-Wire & logika proteksi suhu.
- [ ] **Fase 4: Integrasi ESP32 ke Cloud & Payment Trigger**
  - [ ] ESP32 membaca trigger status ON/OFF dan durasi dari Firebase secara realtime.
  - [ ] Implementasi logika auto-shutdown idle (arus 0A selama 5 menit).
  - [ ] Implementasi logika emergency shutdown (suhu > 60°C).
- [ ] **Fase 5: Pembuatan Realtime Hardware Web Dashboard**
  - [ ] UI Realtime Telemetri Stop Kontak, Sensor PZEM & Sensor DHT22.
  - [ ] Panel Log Transaksi, Countdown Timer, dan Grafik Laba Bersih.
- [ ] **Fase 6: Fabrikasi Casing & Final Assembly**
  - [ ] Perakitan komponen ke dalam casing (Box Panel ABS / 3D Print).
  - [ ] Wiring jalur AC 220V + sekring pengaman 5A/10A.
  - [ ] Full system stress test & debugging.
- [ ] **Fase 7: Dokumentasi Akhir & Pameran Dies Natalis**
  - [ ] Penyusunan laporan akhir untuk MK IoT & Ubiquitous Computing.
  - [ ] Persiapan materi presentasi & demo stan pameran.
