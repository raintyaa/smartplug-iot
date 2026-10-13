# 🧠 Logika Sistem & State Machine — RANOVA Smart Plug IoT

Dokumen ini memuat aturan operasional, alur transaksi, penanganan tombol, logika tampilan LCD, serta sistem keselamatan termal dan finansial yang disepakati pada **21 September 2026**.

---

## 1. Aturan Transaksi & Konkurensi Multi-Slot (3 Slot Independen)

1. **Konkurensi Penuh (Multi-User Concurrent Charging):**
   * Ketiga slot (Slot 1, Slot 2, Slot 3) dapat **aktif mengecas secara bersamaan**.
   * Tiap slot memiliki pewaktu (*countdown timer*) dan relay masing-masing yang berjalan secara paralel.

2. **Kunci Pemilihan Slot (*Mutex / State Lock* saat Transaksi):**
   * Ketika Pengguna A menekan tombol salah satu slot (misal Slot 1) dan sistem masuk ke status `WAITING_PAYMENT` (QRIS muncul):
     * **Tombol slot lain (Slot 2 & 3) DIKUNCI SEMENTARA (diabaikan oleh program)** sampai transaksi Slot 1 selesai atau batas waktu habis.
     * **Fitur Pembatalan Manual (Cancel Selection):** Pengguna dapat membatalkan pilihan slot dengan **menekan kembali tombol slot tersebut**, sehingga slot seketika kembali ke `STANDBY` dan membuka kunci untuk slot lainnya.
   * **Batas Waktu (*Timeout*) QRIS:** Ditetapkan **300 detik (5 menit)** (`QRIS_TIMEOUT_MS = 300000`).
     * Pengguna memiliki waktu 5 menit yang cukup untuk membuka aplikasi mobile banking/e-wallet, memindai QRIS, dan memasukkan PIN.
     * Layar LCD menampilkan hitung mundur waktu tunggu (`Tunggu: xxx s`).
     * Jika dalam 5 menit tidak ada pembayaran masuk: Slot 1 otomatis kedaluwarsa dan kembali ke `STANDBY`, lalu tombol slot lain terbuka kembali.
     * Jika pembayaran berhasil masuk via Webhook: Slot 1 berubah status menjadi `ACTIVE`, relay menyala, LED menyala solid, dan tombol slot lain langsung terbuka kembali untuk disewa oleh pengguna lain secara bersamaan.

3. **Fitur Penghentian Manual Lebih Awal (*Early Stop / Force Finish*):**
   * Pengguna dapat menekan kembali tombol slot yang sedang `ACTIVE` jika selesai mengecas sebelum waktu habis.
   * **Respons Fisik Cepat & Kebal Gangguan EMI (50ms Software Debounce):**
     * Membaca pin tombol GPIO 27, 14, 12 menggunakan *edge detection* digital dengan debounce software 50 ms. Pendekatan ini menggantikan interrupt hardware untuk mengeliminasi pemicu palsu akibat *switching transient* / EMI AC 220V.
     * Begitu tombol ditekan stabil, relay beban dan lampu LED **langsung mati seketika** di level hardware tanpa menunggu respons internet.
   * **Konsekuensi:** Sisa waktu langsung **hangus (dihapus)** dan status slot langsung diperbarui ke `STANDBY` di broker MQTT dan Firebase RTDB.
   * **Proteksi Edge-Detection:** Filter transisi digital mencegah *holding re-trigger* (menahan tombol tidak akan memicu sesi sewa baru tanpa sengaja).

---

## 2. Logika Tampilan Display (LCD 20x4 I2C via FreeRTOS Core 0)

Layar LCD 20x4 I2C diisolasi pada **Core 0 FreeRTOS** (`TaskLCD`, frekuensi refresh 250 ms) agar operasi I2C tidak memblokir komunikasi MQTT, loop sensor PZEM, dan pembacaan tombol pada Core 1.
Layar dirancang dengan sistem prioritas bertingkat (*Priority-Based State Machine*):

1. **State 1: Kondisi Standby (Seluruh Slot Menganggur)**
   * Menampilkan pesan ramah pengguna:
     ```text
     [Line 1]:   SMART  PLUG   
     [Line 2]:  Tekan Tombol...
     ```
2. **State 2: Tombol Ditekan (Menunggu Pembayaran QRIS)**
   * Menampilkan nomor slot dan hitung mundur batas waktu scan 5 menit:
     ```text
     [Line 1]: Scan QRIS Slot 1
     [Line 2]: Tunggu: 295s    
     ```
3. **State 3: Pembayaran Sukses Dikonfirmasi (Prioritas Tertinggi, 4 Detik)**
   * Menampilkan notifikasi sukses transien selama 4 detik:
     ```text
     [Line 1]:  BAYAR BERHASIL 
     [Line 2]:   SLOT 1 AKTIF  
     ```
4. **State 4: Telemetri Sensor Daya saat Slot Aktif (Revisi Final Presisi)**
   * Jika ada minimal 1 slot yang sedang berstatus `ACTIVE`, LCD otomatis beralih menampilkan telemetri daya listrik aktual dari modul PZEM-004T secara bersih (16 karakter pas):
     ```text
     [Line 1]: V: 220V  I: 0.50A
     [Line 2]: Daya: 110.0 Watt
     ```
   * *Catatan Desain:* Nomor slot aktif sengaja tidak dimampatkan ke baris LCD agar teks tidak terpotong. Indikator slot aktif fisik ditunjukkan secara elegan oleh **Lampu LED Ring Tombol** yang menyala solid di panel box, serta countdown detail di Web Dashboard.
5. **State 5: Kondisi Darurat Overheat ($T \ge 42^\circ\text{C}$)**
   * Mengambil alih tampilan LCD untuk menampilkan peringatan bahaya dan angka suhu pendinginan realtime:
     ```text
     [Line 1]: ! OVERHEAT >42C!
     [Line 2]: Suhu Box: 43.1 C
     ```

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

3. **Deteksi Beban Kosong (Idle / Unplugged Load Detection):**
   * **Skenario Single-Slot:** Jika hanya ada 1 slot yang berstatus `ACTIVE` dan PZEM membaca daya $P < 2.0\text{ W}$ ($I < 0.02\text{ A}$), sistem secara definitif mendeteksi bahwa beban/charger belum dicolokkan atau telah dicabut oleh pengguna.
   * **Peringatan Visual Dashboard:** Kartu slot menampilkan indikator `⚠️ Tanpa Beban (0.0 W)` untuk memberikan kepastian bagi operator. Operator dapat menekan tombol **"Matikan Manual"** di dashboard untuk mereset slot yang ditinggalkan pengguna sebelum waktu sewa habis.
   * **Skenario Multi-Slot Bersamaan:** Jika seluruh slot aktif tidak terhubung beban ($P_{\text{total}} < 2.0\text{ W}$), seluruh slot ditandai tanpa beban. Jika ada beban mengalir, dashboard menampilkan daya total agregat stasiun.

4. **Batasan Sistem & Rencana Pengembangan Hardware (System Boundaries & Future Work):**
   * Karena topologi donat CT berada pada kabel Fasa busbar utama, sistem saat ini mengukur akumulasi penjumlahan paralel ($P_{\text{total}} = P_1 + P_2 + P_3$).
   * Untuk memisahkan pembacaan daya beban masing-masing stop kontak secara independen saat multi-slot aktif bersamaan, solusi pengembangan hardware lanjutan adalah memasang sensor arus mikro (seperti modul Hall-effect ACS712 atau donat CT mikro CT-101) pada masing-masing kabel output relay sebelum stop kontak Broco.

---

## 4. Sistem Proteksi Keselamatan Termal (Thermal Hysteresis Protection)

1. **Ambang Batas Suhu Bahaya:** $T \ge 42^\circ\text{C}$ di dalam casing.
2. **Aksi Darurat (*Emergency Cut-off*):**
   * Seluruh relay beban (Channel 1, 2, 3 & Spare) **langsung diputus seketika (OFF)**. Aliran 220V ke seluruh stop kontak mati total.
   * Seluruh lampu LED indikator slot dimatikan.
   * Seluruh tombol fisik dinonaktifkan (dikunci total dari penekanan sewa baru).
   * Status darurat dikirim ke Firebase (`system/emergency_alert = true`, status slot = `EMERGENCY_STOP`).
   * LCD 16x2 tetap menyala menampilkan peringatan bahaya dan **angka suhu realtime** yang sedang mendingin:
     ```text
     [Line 1]: ! OVERHEAT >42C!
     [Line 2]: Suhu Box: xx.x C
     ```
3. **Pemulihan Otomatis (*Auto Recovery / Thermal Hysteresis*):**
   * **Ambang Batas Suhu Aman:** $T < 37^\circ\text{C}$ (selisih histeresis $5^\circ\text{C}$ untuk mencegah *thermal chattering*).
   * Saat suhu turun di bawah $37^\circ\text{C}$, sistem secara otomatis mereset status darurat di Firebase (`system/emergency_alert = false`).
   * Seluruh slot kembali ke mode `STANDBY` (siap disewa kembali).
   * Tombol fisik diaktifkan kembali dan LCD kembali ke tampilan awal `SMART PLUG / Tekan Tombol...`.

