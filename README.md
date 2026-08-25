# ⚡ IoT Smart Plug System Terintegrasi QRIS & ESP32

Proyek akhir mata kuliah Internet of Things (Semester 5) untuk otomatisasi dan monetisasi stop kontak listrik menggunakan pembayaran QRIS berbasis mikrokontroler ESP32, sensor daya listrik, dan web dashboard monitoring realtime.

---

## 📌 Gambaran Umum Sistem

Sistem ini dirancang untuk menyediakan akses daya listrik berbayar (seperti fasilitas *charging station* atau *coworking space*):
1. **Pengguna** memilih stop kontak dengan menekan tombol metal berlampu pada slot yang diinginkan.
2. **Pengguna** melakukan pembayaran melalui stiker QRIS pada panel alat.
3. **Payment Gateway (Midtrans)** memvalidasi pembayaran secara otomatis.
4. **Cloud Database (Firebase)** menerima status pembayaran dan mengaktifkan timer proporsional sesuai nominal bayar.
5. **ESP32** menyalakan relay stop kontak yang dipilih, menyalakan lampu LED ring pada tombol, dan mengukur beban daya listrik via sensor.
6. **Web Dashboard** menampilkan status realtime, sisa waktu hitung mundur (*countdown*), konsumsi daya (Watt/kWh), serta histori transaksi.

---

## 🛠️ Spesifikasi Komponen (Final Bill of Materials)

### Hardware Utama
* **Mikrokontroler:** ESP32 DevKit V1 30-Pin (WiFi 2.4 GHz)
* **Aktuator (Wajib):** Modul Relay 4-Channel 5V Optocoupler (Active LOW)
* **Sensor (Wajib):** Sensor Arus & Daya Listrik **PZEM-004T V3.0** (dilengkapi koil CT)
* **Antarmuka Tombol & Indikator:** 3 unit **Metal Push Button 16mm Self-Reset (Momentary) dengan 5V LED Ring** *(Tombol fisik dan lampu status terintegrasi menjadi satu unit)*
* **Display Lokal:** Modul LCD 16x2 Karakter dengan I2C Backpack tersolder
* **Catu Daya DC:** Switching Power Supply 5V (3A - 5A)
* **Kelistrikan AC 220V:**
  * 3 unit Stop Kontak AC 1-Lubang (Broco / Panasonic)
  * Kabel NYMHY/NYYHY 2x1.5mm
  * Steker AC Arde Male
  * Fuse Holder Panel + Sekring AC 5A/10A (Pengaman arus)
  * Terminal Block Sambungan Kabel
* **Bahan Casing:** Kayu Triplek (Tebal 6mm - 9mm)
* **Bahan Prototyping:** Breadboard 830 titik, Kabel Jumper Dupont (M-M, M-F, F-F), Kabel Micro USB Data

### Software & Cloud Stack
* **Payment Gateway:** Midtrans (Snap API & Webhook Notification)
* **Database & Realtime Sync:** Firebase Realtime Database
* **Firmware ESP32:** C++ (Arduino IDE / PlatformIO)
* **Web Dashboard:** Web App (Monitoring Status, Timer, Log Transaksi, & Grafik Daya Listrik)

---

## 🔌 Pemetaan Pin GPIO ESP32 (Pin Budgeting)

| Komponen | Pin Modul | Pin ESP32 | Keterangan |
|---|---|---|---|
| **LCD 16x2 I2C** | SDA | `GPIO 21` | Jalur Data I2C |
| | SCL | `GPIO 22` | Jalur Clock I2C |
| **Relay 4-Channel** | IN1 (Slot 1) | `GPIO 23` | Kontrol Relay Stop Kontak 1 |
| | IN2 (Slot 2) | `GPIO 19` | Kontrol Relay Stop Kontak 2 |
| | IN3 (Slot 3) | `GPIO 18` | Kontrol Relay Stop Kontak 3 |
| **Sensor PZEM-004T** | RX | `GPIO 17` (TX2) | Komunikasi Serial UART |
| | TX | `GPIO 16` (RX2) | Komunikasi Serial UART |
| **Tombol Metal 16mm** | Tombol 1 (NO) | `GPIO 27` | Input Tombol Slot 1 (Internal Pull-Up) |
| | Tombol 2 (NO) | `GPIO 14` | Input Tombol Slot 2 (Internal Pull-Up) |
| | Tombol 3 (NO) | `GPIO 12` | Input Tombol Slot 3 (Internal Pull-Up) |
| **LED Ring Tombol** | LED Ring 1 (+) | `GPIO 32` | Indikator Lampu Slot 1 Aktif |
| | LED Ring 2 (+) | `GPIO 33` | Indikator Lampu Slot 2 Aktif |
| | LED Ring 3 (+) | `GPIO 25` | Indikator Lampu Slot 3 Aktif |

---

## 📐 Arsitektur Sistem

```
[ Pengguna ] ───(Scan QRIS)───> [ Midtrans Payment Gateway ]
                                             │
                                     (Webhook HTTP POST)
                                             ▼
                                  [ Backend / Cloud API ]
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
         - Kirim Pembacaan Daya ke Cloud          - Kontrol Manual (Admin)
```

---

## 🔑 Panduan Setup Midtrans Sandbox (Payment Gateway)

### 1. Registrasi Akun
1. Daftar akun merchant di [Midtrans Dashboard](https://dashboard.midtrans.com/register).
2. Pastikan indikator lingkungan berada di mode **SANDBOX** (lingkungan uji coba gratis).

### 2. Mengambil Kredensial API
Buka menu **Settings** -> **Access Keys**:
* **Merchant ID:** Simpan ID merchant kalian.
* **Client Key:** `Mid-client-YOUR_CLIENT_KEY`
* **Server Key:** `Mid-server-YOUR_SERVER_KEY`

### 3. Mengaktifkan Channel Pembayaran
1. Buka menu **Settings** -> **Snap Preferences** -> Tab **Payment Channels**.
2. Pastikan channel **GoPay / QRIS** aktif.

### 4. Cara Membuat Tagihan Transaksi via API (Snap Token)
Backend mengirim HTTP POST ke endpoint Midtrans:
* **URL:** `https://app.sandbox.midtrans.com/snap/v1/transactions`
* **Header:**
  * `Content-Type: application/json`
  * `Authorization: Basic <Base64(ServerKey:)>`
* **Body Payload:**
```json
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
  - [x] Brainstorming ide & penentuan spesifikasi hardware/software.
  - [x] Keputusan desain antarmuka: 3 Metal Push Button 16mm dengan LED Ring terintegrasi.
  - [x] Registrasi & konfigurasi akun Midtrans Sandbox.
  - [x] Pengujian transaksi API mandiri via script `create_test_payment.ps1`.
  - [x] Pengajuan aktivasi akun Midtrans Production (Proses Review).
- [ ] **Fase 2: Setup Cloud Database (Firebase) & Pengadaan Komponen**
  - [ ] Pembelian komponen online sesuai Bill of Materials.
  - [ ] Setup Firebase Realtime Database project & security rules.
- [ ] **Fase 3: Perakitan & Pengujian Hardware di Breadboard**
  - [ ] Wiring & coding relay 3-channel + countdown timer millis().
  - [ ] Integrasi display LCD 16x2 I2C & 3 tombol metal berlampu.
  - [ ] Kalibrasi pembacaan tegangan, arus, dan daya dari sensor PZEM-004T.
- [ ] **Fase 4: Integrasi ESP32 ke Firebase & Payment Webhook**
  - [ ] ESP32 membaca trigger status ON/OFF dan durasi dari Firebase.
  - [ ] Webhook backend untuk sinkronisasi otomatis Midtrans -> Firebase.
- [ ] **Fase 5: Pembuatan Web Dashboard**
  - [ ] UI Realtime Status Stop Kontak & Sisa Waktu.
  - [ ] Halaman Log Transaksi & Riwayat Penggunaan Listrik (Watt/kWh).
- [ ] **Fase 6: Fabrikasi Casing Triplek & Final Assembly**
  - [ ] Pembuatan box kayu triplek & pemasangan stop kontak AC 220V + sekring.
  - [ ] Full system stress test & debugging.
- [ ] **Fase 7: Dokumentasi Akhir & Persiapan Pameran Dies Natalis**
  - [ ] Penyusunan laporan akhir.
  - [ ] Persiapan materi presentasi & demo stan pameran.
