# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Operator dan pengelola fasilitas di area publik kampus atau komersial (kantin, perpustakaan, coworking space) yang bertanggung jawab memantau operasional stasiun pengisian daya publik, mencatat omset harian, memantau keselamatan perangkat (suhu/daya listrik), dan dapat memutus aliran listrik dalam kondisi darurat (*Emergency Shutdown*).

## Product Purpose

RANOVA Smart Plug IoT menyediakan infrastruktur pengisian daya mandiri (*self-service*) berbayar QRIS. Tujuannya adalah mengotomatiskan monetisasi stop kontak publik secara adil berbasis durasi waktu, mencegah penggunaan listrik berlebih/ilegal, serta memberikan visibilitas penuh kepada pengelola stasiun mengenai penggunaan daya nyata, biaya listrik PLN, dan laba bersih secara realtime.

## Positioning

Sistem IoT pengisian daya cerdas terintegrasi dari hulu ke hilir: tombol fisik lokal + mikrokontroler ESP32 + gateway pembayaran QRIS otomatis (Mayar/DANA/GoPay) + pemantauan daya presisi tinggi (PZEM-004T & DHT22) + Web Dashboard Operator realtime tanpa ketergantungan pada aplikasi seluler pihak ketiga bagi pengguna.

## Operating Context

- **Lokasi Fisik:** Stasiun fisik 3 stop kontak (Broco Inbow) dengan relay active-LOW, tombol metal 16mm dengan LED ring indikator, sensor PZEM-004T (tegangan, arus, daya, energi kumulatif), sensor suhu DHT22 internal box, dan LCD 16x2 I2C.
- **Lingkungan Software:** Dashboard web lokal berbasis Laravel 11 (PHP 8.4) & MySQL di Laragon, sinkronisasi realtime 2-3 detik dengan Firebase Realtime Database, dan webhook serverless di Vercel.
- **Ritual Operator:** Membuka dashboard setiap hari, memantau apakah ESP32 online/offline, mengawasi suhu box agar di bawah ambang batas overheat (< 60°C), melihat margin laba bersih harian, serta melakukan pemutusan darurat jika terdeteksi anomali.

## Capabilities and Constraints

- **Kontrol 3 Slot Terpisah:** Masing-masing slot memiliki siklus status mandiri: `STANDBY` (Siap) ➔ `WAITING_PAYMENT` (Menunggu Bayar) ➔ `ACTIVE` (Sedang Aktif dengan countdown timer) ➔ `STANDBY`.
- **Pricing:** Default sewa Rp 1.000 per 15 menit (dapat disesuaikan di pengaturan).
- **Tarif Dasar Listrik PLN:** Rp 1.444,70 / kWh untuk perhitungan otomatis estimasi biaya listrik PLN dan margin laba bersih.
- **Keselamatan Listrik:** Fitur pemutus darurat terpusat (*Emergency Shutdown*) yang mematikan seluruh relay seketika dari dashboard web maupun sakelar fisik.

## Brand Commitments

- **Nama Produk:** RANOVA (Smart Plug IoT Station).
- **Karakter Visual:** Industrial matte dark aesthetic (terinspirasi dari Sailio), font monospaced (*JetBrains Mono*) untuk angka telemetri dan timer, serta aksen warna fungsional (*Terminal Mint* `#05DF72` untuk aktif, *Amber* untuk menunggu bayar, dan *Crimson* untuk shutdown darurat).
- **Aturan Baku Desain:** Bebas dari AI Slop (tanpa glassmorphism buram berlebih, tanpa gradasi neon pelangi, tanpa kartu di dalam kartu yang berdesakan, dan wajib memenuhi standar kontras WCAG).

## Evidence on Hand

- File purwarupa HTML hasil kurasi Impeccable (0 Anti-Patterns): `ranova-dashboard-claude.html`.
- Kode firmware ESP32 teruji fisik: `firmware/test/test_3_single_slot_with_pzem/test_3_single_slot_with_pzem.ino`.
- Master QR code transaksi resmi: `RANOVA QRIS.png`.
- Dokumentasi integrasi & wiring: `docs/WIRING_GUIDE.md` dan `docs/INTEGRATION_LOG_AND_TROUBLESHOOTING.md`.

## Product Principles

1. **Safety and Reliability First:** Keamanan fisik dan kestabilan perangkat keras selalu lebih penting daripada estetika; telemetri suhu dan tombol darurat harus selalu terlihat dan mudah diakses.
2. **Operational Clarity:** Data operasional (status slot, waktu tersisa, pendapatan, biaya PLN) harus langsung dipahami dalam 3 detik tanpa beban kognitif berlebih.
3. **Tasteful Restraint:** Desain antarmuka fungsional, bersih, dan berwibawa seperti konsol instrumen industri; hindari dekorasi visual yang tidak memiliki fungsi operasional.
4. **Fair & Transparent Monetization:** Pengguna dan operator mendapatkan transparansi penuh atas durasi waktu yang dibeli dan energi listrik yang digunakan.

## Accessibility & Inclusion

- Memenuhi standar rasio kontras warna WCAG AA (minimal rasio 4.5:1 untuk semua teks fungsional).
- Batas minimal ukuran font fungsional 11px (tidak menggunakan font di bawah 11px).
- Status sistem tidak hanya bergantung pada warna, tetapi juga diperjelas dengan teks status yang eksplisit.
