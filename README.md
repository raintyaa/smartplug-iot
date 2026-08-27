# ⚡ Sistem Smart Plug Berbasis ESP32 Terintegrasi QRIS Payment Gateway dan Monitoring Daya Listrik

Proyek akhir mata kuliah Internet of Things (Semester 5) untuk otomatisasi dan monetisasi stop kontak listrik menggunakan pembayaran QRIS berbasis mikrokontroler ESP32, sensor daya listrik, sensor suhu keselamatan, dan web dashboard monitoring realtime.

---

## 📌 Gambaran Umum Sistem

Sistem ini dirancang untuk menyediakan akses daya listrik berbayar (seperti fasilitas *charging station* atau *coworking space*):
1. **Pengguna** memilih stop kontak dengan menekan tombol metal berlampu pada slot yang diinginkan.
2. **Pengguna** melakukan pembayaran melalui stiker QRIS pada panel alat (nominal menentukan durasi).
3. **Payment Gateway (Midtrans)** memvalidasi pembayaran secara otomatis.
4. **Cloud Database (Firebase)** menerima status pembayaran dan mengaktifkan timer proporsional sesuai nominal bayar.
5. **ESP32** menyalakan relay stop kontak yang dipilih, menyalakan lampu LED ring pada tombol, dan mengukur beban daya listrik via sensor.
6. **Sensor PZEM-004T** memantau konsumsi daya (Watt/kWh) untuk analisis biaya dan deteksi perangkat idle.
7. **Sensor DHT22** memantau suhu internal casing untuk proteksi keselamatan terhadap overheating.
8. **Web Dashboard** menampilkan status realtime, konsumsi daya, suhu internal, serta histori transaksi.

---

## 🛠️ Spesifikasi Komponen (Final Bill of Materials)

### Hardware Utama
* **Mikrokontroler:** ESP32 DevKit V1 30-Pin (WiFi 2.4 GHz)
* **Aktuator:** Modul Relay 4-Channel 5V Optocoupler (Active LOW)
* **Sensor 1 (Daya Listrik):** **PZEM-004T V3.0 100A** dengan Koil CT (Pin Header V3)
  * Mengukur: Tegangan (V), Arus (A), Daya (W), Energi (kWh), Frekuensi (Hz), Power Factor
  * Fungsi: Monitoring konsumsi daya, analisis biaya listrik, dan deteksi idle (auto-shutdown jika tidak ada perangkat dicolokkan)
* **Sensor 2 (Suhu & Kelembaban):** **DHT22**
  * Mengukur: Suhu (°C) dan Kelembaban (%)
  * Fungsi: Proteksi keselamatan terhadap overheating di dalam casing (emergency shutdown jika suhu melebihi batas aman)
* **Antarmuka Tombol & Indikator:** 3 unit **Metal Push Button 16mm Self-Reset (Momentary) 5V LED Ring** (Tombol fisik dan lampu status terintegrasi menjadi satu unit per stop kontak)
* **Display Lokal:** Modul LCD 16x2 Karakter dengan I2C Backpack tersolder
* **Catu Daya DC:** Switching Power Supply 5V (3A - 5A)
* **Kelistrikan AC 220V:**
  * 3 unit Stop Kontak AC 1-Lubang (Broco / Panasonic)
  * Kabel NYMHY/NYYHY 2x1.5mm
  * Steker AC Arde Male
  * Fuse Holder Panel + Sekring AC 5A/10A (Pengaman arus)
  * Terminal Block Sambungan Kabel
* **Casing:** Box Panel ABS (IP65) atau Custom 3D Print (Filamen ABS/PETG)
* **Prototyping:** Breadboard 830 titik, Kabel Jumper Dupont (M-M, M-F, F-F), Kabel Micro USB Data

### Software & Cloud Stack
* **Payment Gateway:** Midtrans (Snap API & Webhook Notification)
* **Database & Realtime Sync:** Firebase Realtime Database
* **Firmware ESP32:** C++ (Arduino IDE / PlatformIO)
* **Web Dashboard:** Web App (Monitoring Status, Timer, Log Transaksi, Grafik Daya & Suhu)

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
| **Sensor DHT22** | DATA | `GPIO 4` | Data Suhu & Kelembaban (Pull-up 4.7kΩ) |
| **Tombol Metal 16mm** | Tombol 1 (NO) | `GPIO 27` | Input Tombol Slot 1 (Internal Pull-Up) |
| | Tombol 2 (NO) | `GPIO 14` | Input Tombol Slot 2 (Internal Pull-Up) |
| | Tombol 3 (NO) | `GPIO 12` | Input Tombol Slot 3 (Internal Pull-Up) |
| **LED Ring Tombol** | LED Ring 1 (+) | `GPIO 32` | Indikator Lampu Slot 1 Aktif |
| | LED Ring 2 (+) | `GPIO 33` | Indikator Lampu Slot 2 Aktif |
| | LED Ring 3 (+) | `GPIO 25` | Indikator Lampu Slot 3 Aktif |

**Total Pin Terpakai: 14 pin** dari 25+ pin GPIO tersedia.

---

## 📐 Arsitektur Sistem

```
[ Pengguna ] ───(Scan QRIS)───> [ Midtrans Payment Gateway ]
                                             │
                                     (Webhook HTTP POST)
                                             ▼
                                  [ Backend / Cloud Function ]
                                             │
                                       (Update Data)
                                             ▼
                               [ Firebase Realtime Database ]
                                             │
                        ┌────────────────────┴────────────────────┐
                        ▼                                         ▼
                [ Perangkat ESP32 ]                      [ Web Dashboard ]
         - Aktifkan Relay & Timer                 - Status Stop Kontak Realtime
         - Nyalakan Ring LED Tombol               - Countdown Timer Sisa Waktu
         - Baca Sensor PZEM (Watt/kWh)            - Log Transaksi & Konsumsi Daya
         - Baca Sensor DHT22 (Suhu °C)            - Monitoring Suhu Internal Casing
         - Auto-Shutdown: Idle & Overheat         - Grafik Daya & Suhu Historis
```

---

## 🔒 Sistem Proteksi Otomatis (3 Skenario Relay OFF)

| Skenario | Pemicu | Sensor Terlibat |
|---|---|---|
| **Timer Habis** | Durasi waktu yang dibeli pengguna sudah berakhir | Logika timer ESP32 |
| **Auto-Shutdown Idle** | Tidak ada perangkat dicolokkan selama 5 menit (arus ≈ 0A) | **Sensor PZEM-004T** |
| **Emergency Shutdown Suhu** | Suhu internal casing melebihi 60°C | **Sensor DHT22** |

---

## 💰 Analisis Biaya & Keuntungan (Fitur Dashboard)

Sensor PZEM-004T mengukur kWh secara akumulatif, sehingga sistem dapat menghitung:
* **Biaya Listrik PLN:** `kWh terpakai x Rp 1.444/kWh`
* **Total Pendapatan QRIS:** Jumlah seluruh transaksi masuk
* **Laba Bersih:** `Total Pendapatan - Total Biaya Listrik`

---

## 🔑 Panduan Setup Midtrans Sandbox

### 1. Registrasi Akun
1. Daftar akun merchant di [Midtrans Dashboard](https://dashboard.midtrans.com/register).
2. Pastikan lingkungan berada di mode **SANDBOX**.

### 2. Kredensial API
Buka menu **Settings** -> **Access Keys**:
* **Merchant ID:** Simpan ID merchant kalian.
* **Client Key:** `Mid-client-YOUR_CLIENT_KEY`
* **Server Key:** `Mid-server-YOUR_SERVER_KEY`

### 3. Mengaktifkan Channel Pembayaran
1. Buka menu **Settings** -> **Snap Preferences** -> Tab **Payment Channels**.
2. Pastikan channel **GoPay / QRIS** aktif di posisi paling atas.

### 4. Membuat Tagihan Transaksi via API (Snap Token)
```json
POST https://app.sandbox.midtrans.com/snap/v1/transactions
Header: Authorization: Basic <Base64(ServerKey:)>

{
  "transaction_details": {
    "order_id": "ORDER-001",
    "gross_amount": 2000
  }
}
```

---

## 📋 Roadmap Progres (14 Minggu)

- [x] **Fase 1: Riset, Desain Sistem & Setup Payment Gateway (SELESAI)**
  - [x] Brainstorming ide & penentuan arsitektur sistem.
  - [x] Finalisasi spesifikasi hardware: 2 sensor (PZEM-004T + DHT22), relay 4-ch, 3 tombol metal 16mm LED ring.
  - [x] Keputusan casing: Box Panel ABS (IP65) atau Custom 3D Print (ABS/PETG).
  - [x] Registrasi & konfigurasi akun Midtrans Sandbox.
  - [x] Pengujian transaksi API & simulasi pembayaran QRIS berhasil (Settlement).
  - [x] Pengajuan aktivasi akun Midtrans Production (Proses Review).
  - [x] Konsultasi dengan asisten dosen: validasi aktuator (relay), penambahan sensor kedua (DHT22), dan fitur auto-shutdown.
- [ ] **Fase 2: Setup Cloud Database (Firebase) & Pengadaan Komponen**
  - [ ] Pembelian komponen online sesuai Bill of Materials.
  - [ ] Setup Firebase Realtime Database project & security rules.
  - [ ] Desain CAD casing (jika 3D Print) atau pembelian Box Panel ABS.
- [ ] **Fase 3: Perakitan & Pengujian Hardware di Breadboard**
  - [ ] Wiring & coding relay 3-channel + countdown timer millis().
  - [ ] Integrasi display LCD 16x2 I2C & 3 tombol metal berlampu.
  - [ ] Kalibrasi pembacaan tegangan, arus, dan daya dari sensor PZEM-004T.
  - [ ] Integrasi sensor DHT22 & logika proteksi suhu.
- [ ] **Fase 4: Integrasi ESP32 ke Firebase & Payment Webhook**
  - [ ] ESP32 membaca trigger status ON/OFF dan durasi dari Firebase.
  - [ ] Webhook backend untuk sinkronisasi otomatis Midtrans -> Firebase.
  - [ ] Implementasi logika auto-shutdown idle (arus 0A selama 5 menit).
  - [ ] Implementasi logika emergency shutdown (suhu > 60°C).
- [ ] **Fase 5: Pembuatan Web Dashboard**
  - [ ] UI Realtime Status Stop Kontak & Sisa Waktu.
  - [ ] Halaman Log Transaksi & Riwayat Penggunaan Listrik (Watt/kWh).
  - [ ] Panel Monitoring Suhu Internal & Status Keselamatan.
  - [ ] Grafik Analisis Biaya Listrik & Keuntungan.
- [ ] **Fase 6: Fabrikasi Casing & Final Assembly**
  - [ ] Fabrikasi casing (3D print atau Box Panel ABS) & pemasangan stop kontak AC 220V + sekring.
  - [ ] Full system stress test & debugging.
- [ ] **Fase 7: Dokumentasi Akhir & Persiapan Pameran Dies Natalis**
  - [ ] Penyusunan laporan akhir.
  - [ ] Persiapan materi presentasi & demo stan pameran.
