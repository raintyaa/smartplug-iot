# ⚡ IoT Smart Plug System Terintegrasi QRIS & ESP32

Proyek akhir mata kuliah Internet of Things (Semester 5) untuk otomatisasi dan monetisasi stop kontak listrik menggunakan pembayaran QRIS berbasis mikrokontroler ESP32, sensor daya listrik, dan web dashboard monitoring realtime.

---

## 📌 Gambaran Umum Sistem

Sistem ini dirancang untuk menyediakan akses daya listrik berbayar (seperti fasilitas *charging station* atau *coworking space*):
1. **Pengguna** memilih stop kontak dan melakukan pembayaran melalui QRIS.
2. **Payment Gateway (Midtrans)** memvalidasi pembayaran secara otomatis.
3. **Cloud Database (Firebase)** menerima status pembayaran dan mengaktifkan timer.
4. **ESP32** menyalakan relay stop kontak yang dipilih dan mengukur beban daya listrik.
5. **Web Dashboard** menampilkan status realtime, sisa waktu hitung mundur (*countdown*), konsumsi daya (Watt/kWh), serta histori transaksi.

---

## 🛠️ Spesifikasi Komponen

### Hardware
* **Mikrokontroler:** ESP32 DevKit V1 (WiFi 2.4 GHz)
* **Aktuator:** Modul Relay 4-Channel 5V (Active LOW)
* **Sensor:** Sensor Daya & Arus Listrik (PZEM-004T / ACS712)
* **Display Lokal:** LCD 16x2 dengan Modul I2C Backpack
* **Input Lokal:** Push Button (Pemilihan stop kontak 1, 2, atau 3)
* **Indikator:** LED 5mm (Status aktif per stop kontak)
* **Catu Daya:** Switching Power Supply 5V (3A - 5A)
* **Casing:** Box Panel berbahan dasar kayu triplek

### Software & Cloud
* **Payment Gateway:** Midtrans (Snap API & Webhook Simulator)
* **Database & Auth:** Firebase Realtime Database
* **Firmware:** C++ (Arduino IDE / PlatformIO)
* **Web Dashboard:** HTML5, CSS3, JavaScript / React (Monitoring & Logging)

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
         - Baca Sensor Daya (Watt/kWh)            - Countdown Timer Sisa Waktu
         - Kirim Pembacaan Sensor ke Cloud        - Log Transaksi & Konsumsi Daya
```

---

## 🔑 Panduan Setup Midtrans Sandbox (Payment Gateway)

### 1. Registrasi Akun
1. Daftar akun merchant di [Midtrans Dashboard](https://dashboard.midtrans.com/register).
2. Pastikan indikator lingkungan berada di mode **SANDBOX** (lingkungan uji coba gratis).

### 2. Mengambil Kredensial API
Buka menu **Settings** -> **Access Keys**:
* **Merchant ID:** `M803958718`
* **Client Key:** `Mid-client-n4HIEKM9_M4FaMxA`
* **Server Key:** `Mid-server-OAsfK1-Okq6idNPy00No2DhQ`

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

### 5. Cara Menjalankan Script Pengujian Mandiri
Buka terminal PowerShell di folder proyek ini dan jalankan:
```powershell
.\create_test_payment.ps1
```
Script akan otomatis menghasilkan link pembayaran QRIS untuk disimulasikan.

---

## 📋 Roadmap Progres (14 Minggu)

- [x] **Fase 1: Riset & Setup Payment Gateway (SELESAI)**
  - [x] Brainstorming ide & arsitektur sistem Smart Plug QRIS.
  - [x] Registrasi & konfigurasi Midtrans Sandbox.
  - [x] Pengambilan & pengamanan API Access Keys (Merchant ID, Client Key, Server Key).
  - [x] Pengujian transaksi API mandiri via script `create_test_payment.ps1`.
  - [x] Simulasi pembayaran QRIS sukses berstatus *Settlement*.
- [ ] **Fase 2: Setup Cloud Database & Pengadaan Komponen**
  - [ ] Setup Firebase Realtime Database.
  - [ ] Pengadaan komponen hardware (ESP32, Relay, Sensor PZEM/ACS712, LCD, Triplek).
- [ ] **Fase 3: Perakitan & Pengujian Hardware di Breadboard**
  - [ ] Wiring & coding relay 3-channel + countdown timer.
  - [ ] Integrasi display LCD 16x2 I2C & tombol pemilih.
  - [ ] Kalibrasi pembacaan sensor daya (Watt & kWh).
- [ ] **Fase 4: Integrasi ESP32 ke Firebase & Payment Webhook**
  - [ ] ESP32 membaca data trigger ON/OFF dari Firebase.
  - [ ] Webhook backend untuk sinkronisasi otomatis Midtrans -> Firebase.
- [ ] **Fase 5: Pembuatan Web Dashboard**
  - [ ] UI Realtime Status Stop Kontak & Sisa Waktu.
  - [ ] Halaman Log Transaksi & Riwayat Penggunaan Listrik.
- [ ] **Fase 6: Fabrikasi Casing Triplek & Final Assembly**
  - [ ] Pembuatan box kayu triplek & pemasangan stop kontak AC 220V + sekring.
  - [ ] Full system stress test & debugging.
- [ ] **Fase 7: Dokumentasi Akhir & Persiapan Pameran Dies Natalis**
  - [ ] Penyusunan laporan akhir.
  - [ ] Persiapan materi presentasi & demo stan.
