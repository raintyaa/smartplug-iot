# 🧠 Logika Sistem & State Machine — RANOVA Smart Plug IoT

Dokumen ini memuat aturan operasional, alur transaksi, penanganan tombol, logika tampilan LCD, serta sistem keselamatan termal dan finansial yang disepakati pada **21 September 2026**.

---

## 1. Aturan Transaksi & Konkurensi Multi-Slot (3 Slot Independen)

1. **Konkurensi Penuh (Multi-User Concurrent Charging):**
   * Ketiga slot (Slot 1, Slot 2, Slot 3) dapat **aktif mengecas secara bersamaan**.
   * Tiap slot memiliki pewaktu (*countdown timer*) dan relay masing-masing yang berjalan secara paralel.

2. **Kunci Pemilihan Slot (*Mutex / State Lock* saat Transaksi):**
   * Ketika Pengguna A menekan tombol salah satu slot (misal Slot 1) dan sistem masuk ke status `WAITING_PAYMENT` (QRIS muncul):
     * **Tombol slot lain (Slot 2 & 3) DIKUNCI TOTAL (diabaikan oleh program)** sampai transaksi Slot 1 selesai atau batas waktu habis.
     * Pengguna tidak dapat membatalkan manual dengan menekan tombol kembali.
   * **Batas Waktu (*Timeout*) QRIS:** Ditetapkan tepat **60 detik (1 menit)**.
     * Jika dalam 60 detik tidak ada pembayaran masuk: Slot 1 otomatis kembali ke `STANDBY`, dan tombol slot lain terbuka kembali.
     * Jika pembayaran berhasil masuk via Webhook: Slot 1 berubah status menjadi `ACTIVE`, relay menyala, LED menyala solid, dan tombol slot lain langsung terbuka kembali untuk disewa oleh pengguna lain.

3. **Fitur Penghentian Manual Lebih Awal (*Early Stop / Force Finish*):**
   * Pengguna dapat menekan kembali tombol slot yang sedang `ACTIVE` jika selesai mengecas sebelum waktu habis.
   * **Konsekuensi:** Sisa waktu langsung **hangus (dihapus)** dan status slot langsung kembali ke `STANDBY` (Relay OFF, LED OFF).
   * Pengguna tidak bisa melanjutkan sisa waktu sebelumnya jika menekan tombol lagi (harus bayar dari awal).

---

## 2. Logika Tampilan Display (LCD 16x2 I2C)

Layar LCD dirancang dengan pendekatan **Minimalis Berbasis Notifikasi Event**, bukan pemantauan terus-menerus:

1. **State 1: Kondisi Standby (Tidak Ada Transaksi Baru)**
   * Menampilkan kalimat pesan default ramah pengguna:
     ```text
     [Line 1]: RANOVA SMARTPLUG
     [Line 2]: TEKAN TOMBOL SLOT
     ```
2. **State 2: Tombol Ditekan (Menunggu Pembayaran)**
   * Menampilkan instruksi pembayaran dan slot yang dipilih:
     ```text
     [Line 1]: SLOT 1 DIPILIH
     [Line 2]: SCAN QRIS DI WEB
     ```
3. **State 3: Pembayaran Sukses Dikonfirmasi**
   * Menampilkan notifikasi sukses selama 3–4 detik:
     ```text
     [Line 1]: PEMBAYARAN OKE!
     [Line 2]: SLOT 1 AKTIF
     ```
4. **State 4: Kembali ke Standby**
   * Setelah notifikasi sukses selesai, layar LCD **langsung kembali ke tampilan awal (State 1)**.
   * Tidak perlu menampilkan *countdown* berulang-ulang di LCD agar tidak membuat layar penuh/berkedip.
   * **Indikator Slot Aktif:** Pengguna cukup melihat **Lampu LED Ring Tombol** yang menyala solid pada slot yang aktif, serta memantau countdown detail melalui **Web Dashboard**.

---

## 3. Sensor Daya Listrik (PZEM-004T) & Kalkulasi Finansial

1. **Topologi Pengukuran:**
   * 1 modul PZEM-004T dipasang pada kabel Fasa utama PLN sebelum masuk ke percabangan relay.
   * Sensor mengukur **Akumulasi Total Daya Seluruh Box** ($V$, $A$, $W$, dan $kWh$).

2. **Perhitungan Biaya & Penetapan Tarif:**
   * **Biaya Listrik Riil (PLN):**
     $$\text{Biaya PLN} = \text{Total kWh (PZEM)} \times \text{Tarif Dasar PLN (Rp 1.444,70 / kWh)}$$
   * **Total Pendapatan:**
     $$\text{Total Pendapatan} = \sum \text{Nominal Transaksi QRIS}$$
   * **Margin / Laba Operasional:**
     $$\text{Laba Bersih} = \text{Total Pendapatan} - \text{Biaya PLN}$$
   * Data ini dikirim ke Firebase dan ditampilkan pada Web Dashboard Admin untuk mengevaluasi kewajaran tarif sewa per menit.

---

## 4. Sistem Proteksi Keselamatan Termal (DHT22 Overheat Cut-off)

1. **Ambang Batas Suhu Bahaya:** $T > 60^\circ\text{C}$ di dalam casing kayu.
2. **Aksi Darurat (*Emergency Cut-off*):**
   * Seluruh relay (Channel 1, 2, 3) **langsung dimatikan paksa seketika (OFF)**.
   * Status seluruh slot di Firebase langsung di-reset ke `STANDBY` / `EMERGENCY_STOP`.
   * Seluruh sisa waktu sewa **langsung dibatalkan** demi keamanan sirkuit dan mencegah beban komputasi/penyimpanan state berlebih pada ESP32.
   * LCD menampilkan peringatan:
     ```text
     [Line 1]: ! SUHU OVERHEAT !
     [Line 2]: SISTEM DIMATIKAN
     ```
