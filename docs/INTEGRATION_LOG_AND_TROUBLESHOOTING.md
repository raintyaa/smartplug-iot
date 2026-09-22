# 📘 Catatan Integrasi & Panduan Pemecahan Masalah (Troubleshooting Guide)
## Proyek RANOVA Smart Plug IoT — Multi-Slot QRIS & Power Monitoring
**Tanggal Finalisasi Tes 3 Terpadu:** 21 September 2026  
**Status Pengujian:** LULUS 100% (Single-Slot + PZEM-004T + DHT22 + LCD 16x2 + Relay + Tombol Metal + Mayar Webhook + Web Dashboard Laravel)

---

## 📑 Daftar Isi
1. [Ringkasan Capaian Tes 3 Terpadu](#1-ringkasan-capaian-tes-3-terpadu)
2. [Hardware Fisik & Kelistrikan AC 220V](#2-hardware-fisik--kelistrikan-ac-220v)
3. [Jaringan, Webhook Cloud & Sinkronisasi Firebase RTDB](#3-jaringan-webhook-cloud--sinkronisasi-firebase-rtdb)
4. [Logika Display LCD 16x2 untuk Replikasi 3 Slot](#4-logika-display-lcd-16x2-untuk-replikasi-3-slot)
5. [Web Dashboard Operator (Laravel)](#5-web-dashboard-operator-laravel)
6. [Tabel Troubleshooting Cepat (Troubleshooting Matrix)](#6-tabel-troubleshooting-cepat-troubleshooting-matrix)

---

## 1. Ringkasan Capaian Tes 3 Terpadu

Pengujian fisik terpadu berhasil memverifikasi interaksi menyeluruh dari ujung ke ujung (*End-to-End Cyber-Physical Flow*):
* **Fisik ke Cloud:** Tombol fisik ditekan ➔ LED berkedip ➔ ESP32 memperbarui status `WAITING_PAYMENT` ke Firebase.
* **Fintech ke Cloud:** Transaksi QRIS Mayar ➔ Webhook Vercel menerima event pembayaran ➔ Firebase status diperbarui ke `ACTIVE`.
* **Cloud ke Fisik:** ESP32 mendeteksi status `ACTIVE` ➔ Relay 1 berbunyi "KLIK" ON ➔ LED ring menyala solid ➔ Stop Kontak Broco menyalurkan daya 220V.
* **Telemetri Nyata:** PZEM-004T membaca tegangan riil PLN **223.3V – 224.0V** dan daya beban nyata **1.7W – 1.9W** secara konsisten.
* **Proteksi Suhu:** Sensor DHT22 memantau suhu internal box kayu tetap aman pada kisaran **30.3°C – 30.5°C**.
* **Interupsi Pengguna (*Early Stop*):** Menekan kembali tombol fisik saat status aktif seketika mematikan relay dan mereset sistem ke STANDBY.
* **Kontrol Jarak Jauh Web Dashboard:** Tombol "Matikan Manual" pada web dashboard operator teruji memutus relay secara realtime.

---

## 2. Hardware Fisik & Kelistrikan AC 220V

### A. Donat Hitam CT (Current Transformer)
* **Aturan Mutlak:** HANYA 1 kabel Fasa (Hitam/Coklat) yang boleh menembus lubang tengah donat CT!
* **Penyebab Masalah yang Pernah Terjadi:** Jika kabel Netral (Biru) ikut dimasukkan bersama kabel Fasa ke dalam lubang donat, fluks magnetik kedua arus bolak-balik akan saling meniadakan ($\Phi_{total} = \Phi_{Fasa} - \Phi_{Netral} \approx 0$). Akibatnya, arus dan daya akan selalu terbaca **0.0 Watt** meskipun beban dicolokkan.
* Kabel dari donat CT disambungkan ke 2 terminal sekrup bertuliskan **CT** pada modul PZEM.

### B. Karakteristik Suplai Daya PZEM-004T v3.0
* Modul PZEM-004T v3.0 terisolasi secara galvanik dengan optocoupler dan memiliki 2 sumber daya terpisah:
  1. **Sisi Tegangan Tinggi (Terminal Baut L & N):** Mengambil daya langsung dari AC 220V PLN. Chip pengukuran utama di dalam PZEM ditenagai dari sini!
  2. **Sisi Logika UART (Header 4 Pin):** Ditenagai oleh DC 5V dari ESP32 (pin VIN / PSU 5V).
* **Penting:** Jika Steker AC 220V dicabut dari stop kontak dinding PLN, chip PZEM akan **mati total** dan tidak akan merespons UART ke ESP32 sama sekali (pembacaan akan menghasilkan nilai `NAN` / 0.0V).
* **Pin UART:**
  * `5V` PZEM ➔ **VIN** ESP32 (Wajib 5V, jangan 3.3V agar optocoupler membuka sempurna).
  * `GND` PZEM ➔ **GND** ESP32.
  * `TX` PZEM ➔ **GPIO 16** (RX2 ESP32).
  * `RX` PZEM ➔ **GPIO 17** (TX2 ESP32).
* **Kabel Jumper Longgar:** Konektor dupont female-to-female pada pin header PZEM mudah goyang saat sirkuit dirapikan. Jika tegangan tiba-tiba terbaca 0V padahal steker terpasang, periksa dan rapatkan kembali pin header ini.

### C. Pembacaan Arus pada Beban Sangat Kecil
* Pada beban charger HP ($1.8\text{ W}$ / $224\text{ V}$), arus listrik riil yang mengalir adalah:
  $$I = \frac{1.8}{224} \approx 0.008\text{ A}\ (8\text{ mA})$$
* Karena format cetak serial monitor menggunakan 2 desimal (`%.2f`), angka $0.008\text{A}$ dibulatkan menjadi `0.00A` atau `0.01A`. Arus akan terbaca jelas (misal $0.20\text{A} - 0.30\text{A}$) jika menggunakan beban lebih besar seperti charger laptop ($\approx 45\text{W} - 65\text{W}$).

### D. Modul Relay 4-Channel (Active-LOW)
* `LOW = ON` (Relay mengalirkan listrik 220V ke Broco).
* `HIGH = OFF` (Relay memutus listrik).
* Agar relay tidak berbunyi "klik" sesaat (*glitch*) saat ESP32 pertama kali dinyalakan, wajib menuliskan `digitalWrite(PIN_RELAY, HIGH)` pada fungsi `setup()`.

---

## 3. Jaringan, Webhook Cloud & Sinkronisasi Firebase RTDB

### A. Penghapusan Logika Timeout di Webhook Vercel
* **Bug Lama:** Webhook Vercel sebelumnya membandingkan waktu kalender dunia server (`Date.now()`) dengan uptime ESP32 (`millis()`). Selisih waktu yang dihasilkan mencapai 1,78 miliar detik (56 tahun), sehingga webhook langsung membatalkan pembayaran dan menganggap transaksi kedaluwarsa.
* **Solusi Final:** Logika pengecekan selisih waktu di Webhook Vercel telah **dihapus total**. Otoritas batas waktu tunggu pembayaran berada sepenuhnya di tangan mikrokontroler ESP32 (`QRIS_TIMEOUT_MS`).

### B. Sinkronisasi Struktur Node Ganda (*Dual-Node Sync*)
* Untuk menjamin kompatibilitas mutlak antara Webhook Mayar, Dashboard Laravel, dan Firmware ESP32, seluruh status slot ditulis dan dibaca secara simultan ke dua node:
  * `/slots/slotX` (Struktur node utama webhook Mayar & transactions).
  * `/slotX` (Struktur node dashboard dan kompatibilitas kontrol).
  * `/system/active_selection` (Kunci pemilihan slot transaksi berjalan).
* Saat fungsi `setSlotStandby()` berjalan di ESP32, kedua node (`/slots/slot1` dan `/slot1`) beserta `/system/active_selection` di-reset serentak ke `STANDBY` dan `IDLE`.

### C. Algoritma Cerdas *Smart Fallback* pada Webhook
* Jika pengguna mengklik tombol **"Retry"** di dashboard Mayar sementara `active_selection` sudah kembali ke `IDLE`, webhook secara cerdas memindai node slot di Firebase. Jika mendapati salah satu slot masih berstatus `WAITING_PAYMENT`, webhook langsung mengaktifkan slot tersebut tanpa menolak pembayaran.

### D. Durasi Timeout & Fitur Refresh Timer
* Batas waktu tunggu pembayaran `QRIS_TIMEOUT_MS` ditetapkan **180 detik (3 Menit)** agar pengguna memiliki waktu santai untuk membuka aplikasi mobile banking, memindai QRIS, dan memasukkan PIN.
* **Fitur Refresh:** Jika pengguna menekan tombol slot kembali saat status masih `WAITING_PAYMENT`, waktu tunggu 180 detik otomatis diperpanjang dari awal tanpa membatalkan transaksi.

---

## 4. Logika Display LCD 16x2 untuk Replikasi 3 Slot

Ketika sistem direplikasi menjadi **3 Slot Stop Kontak Penuh**, aturan tampilan layar LCD 16x2 adalah sebagai berikut:

1. **LCD Bebas Monopoli:** Layar LCD 16x2 hanya ada 1 unit untuk melayani 3 stop kontak. LCD **TIDAK BOLEH** menampilkan hitung mundur sisa waktu sewa secara terus-menerus.
2. **Alur Notifikasi Event:**
   * **Standby:** `RANOVA SMARTPLUG / TEKAN TOMBOL...`
   * **Saat Tombol Slot Ditekan:** `SLOT X DIPILIH / SCAN QRIS MAYAR`
   * **Saat Pembayaran Dikonfirmasi:** Menampilkan konfirmasi `PEMBAYARAN OKE! / SLOT X AKTIF` selama **3 hingga 4 detik saja**.
   * **Kembali ke Standby:** Setelah 4 detik, LCD otomatis kembali ke tampilan Standby agar calon pengguna lain dapat langsung menyewa Slot berikutnya.
3. **Indikator Fisik:** Status aktif pada alat fisik cukup ditunjukkan oleh **Lampu LED Ring Tombol** yang menyala solid pada slot yang sedang aktif.
4. **Monitoring Sisa Waktu:** Seluruh hitung mundur menit:detik dan grafik Watt PZEM dipantau melalui **Web Dashboard Operator**.

---

## 5. Web Dashboard Operator (Laravel)

* **Lokasi Folder Proyek:** `d:\Rakha\File Kuliah\Semester 5\IoT\Project\ranova-dashboard`
* **Cara Cepat Menjalankan (1-Click):**
  Klik ganda file [**`Project/jalankan_dashboard.bat`**](file:///d:/Rakha/File%20Kuliah/Semester%205/IoT/Project/jalankan_dashboard.bat). File ini otomatis menyalakan server Laravel dan membukakan browser ke `http://127.0.0.1:8000/login`.
* **Cara Manual via Terminal:**
  ```powershell
  cd "d:\Rakha\File Kuliah\Semester 5\IoT\Project\ranova-dashboard"
  php artisan serve
  ```
* **Kredensial Login Operator:**
  * URL: `http://127.0.0.1:8000/login`
  * Email: `admin@ranova.id`
  * Password: `admin123`
* **Fitur Remote:** Tombol "Matikan Manual" per-slot dan "Emergency Stop" global menulis langsung ke Firebase RTDB dan seketika dieksekusi oleh ESP32.

---

## 6. Tabel Troubleshooting Cepat (Troubleshooting Matrix)

| Gejala Masalah | Kemungkinan Penyebab | Tindakan Solusi Cepat |
|---|---|---|
| **PZEM membaca `0.0V` & `0.0W` (Semua 0)** | 1. Steker AC 220V belum tertancap ke dinding.<br>2. Kabel jumper TX/RX kendor atau terbalik.<br>3. Baut L & N menjepit kulit kabel bukan tembaga. | 1. Tancapkan steker AC ke stop kontak PLN.<br>2. Pastikan TX PZEM ➔ GPIO 16 dan RX PZEM ➔ GPIO 17.<br>3. Kencangkan baut terminal AC ke tembaga serabut. |
| **Tegangan `220V` terbaca, tapi Daya tetap `0.0W` saat ada beban** | 1. Kabel Netral ikut masuk ke dalam lubang CT.<br>2. Kabel CT kecil lepas dari baut PZEM.<br>3. Beban belum dinyalakan. | 1. Keluarkan kabel Netral dari donat CT! Hanya kabel Fasa hitam yang boleh masuk.<br>2. Kencangkan 2 baut CT kecil di modul PZEM. |
| **Tombol ditekan tapi cepat sekali timeout (dalam hitungan milidetik)** | Underflow variabel `millis()` saat perhitungan selisih waktu. | Gunakan `millis() - waitStartMs >= QRIS_TIMEOUT_MS` secara langsung (sudah dipatch di Tes 3). |
| **Bayar di Mayar sukses, tapi relay tidak kunjung menyala** | 1. `active_selection` sudah keburu timeout kembali ke `IDLE`.<br>2. Node Firebase tidak sinkron. | 1. Tekan tombol fisik slot dulu sebelum bayar/retry di Mayar.<br>2. Pastikan webhook Vercel versi terbaru aktif (sudah auto-deploy). |
| **Relay langsung aktif tanpa ditekan tombol saat pertama dinyalakan** | Karakteristik Active-LOW: pin output default bernilai LOW saat inisialisasi. | Pastikan `digitalWrite(PIN_RELAY, HIGH)` dipanggil sebelum relay digunakan di `setup()`. |
| **Dashboard Web Laravel tidak bisa dibuka** | Layanan MySQL XAMPP belum aktif atau artisan serve belum berjalan. | 1. Buka XAMPP, klik Start pada MySQL.<br>2. Klik ganda `jalankan_dashboard.bat`. |

---

## 7. Catatan Pengadaan Komponen & Desain Wiring 3 Slot (22 September 2026)

Berdasarkan analisis kebutuhan fisik dan kepraktisan perakitan 3 slot stop kontak:

### A. Komponen yang Divalidasi & Dibeli:
1. **Konektor Tuas Percabangan (WAGO PCT-215 / 5 Lubang 1 Sisi):**
   * **Jumlah:** 10 pcs (~Rp 5.000 / pcs).
   * **Alasan Pemilihan:** Semua 5 lubang menyatu pada 1 lempeng tembaga busbar internal. Pas untuk membagi Fasa PLN ke 3 Relay + PSU + PZEM, dan Netral PLN ke 3 Stop Kontak + PZEM + PSU tanpa pelintiran kabel manual.
2. **Kabel Listrik Serabut Tunggal NYA-F 1.5mm² (AWG 16) Tembaga Murni:**
   * **Jumlah:** Total 4 Meter (~Rp 10.000 / meter).
   * **Varian Warna yang Dipilih:**
     * 🔴 **2 Meter MERAH:** Khusus jalur Fasa / Setrum (220V AC).
     * ⚪ **2 Meter PUTIH:** Khusus jalur Netral (AC Neutral).
   * **Alasan Pemilihan:** Standar kelistrikan beban 220V PLN (mampu menahan arus hingga 15–20A / 3.000W+), menjamin stop kontak tidak panas atau meleleh saat dicolok beban daya besar.

### B. Mekanisme Tombol Metal 16mm (Slot 1, 2, 3):
* **Tanpa Soket Tambahan:** Menggunakan kabel jumper female/solder manual langsung ke pin tombol.
* **Estafet Pin Ground (Daisy-Chain):**
  * Pin `C (Common)` dan Pin `LED (-)` pada masing-masing tombol digabung dan diestafetkan (dijumper antar-tombol), lalu berakhir pada 1 kabel tunggal menuju WAGO DC GND.
  * Pin `NO` masing-masing tombol ditarik independen ke pin GPIO ESP32 (Slot 1: GPIO 27, Slot 2: GPIO 14, Slot 3: GPIO 12).
  * Pin `LED (+)` ditarik independen ke pin GPIO LED ESP32 (Slot 1: GPIO 32, Slot 2: GPIO 33, Slot 3: GPIO 25).
  * Pin `NC` dibiarkan kosong dan diisolasi dengan heatshrink.
