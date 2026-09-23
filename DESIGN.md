---
name: RANOVA Smart Plug IoT Station
description: Industrial matte dark IoT charging station dashboard with crisp telemetry and zero AI-slop.
colors:
  base: "#0A0A0D"
  surface: "#131318"
  surface2: "#181820"
  hairline: "#282832"
  mint: "#05DF72"
  amber: "#F59E0B"
  crimson: "#EF4444"
  text-primary: "#FFFFFF"
  text-secondary: "#A3A3A3"
  text-muted: "#737373"
typography:
  display:
    fontFamily: "Inter, sans-serif"
    fontWeight: 600
  numbers:
    fontFamily: "JetBrains Mono, monospace"
    fontWeight: 700
  body:
    fontFamily: "Inter, sans-serif"
    fontWeight: 400
rounded:
  sm: "6px"
  md: "8px"
  lg: "12px"
  xl: "16px"
---

## Overview

RANOVA Smart Plug IoT Station mengadopsi estetika *Industrial Matte Dark* (terinspirasi dari Sailio, Vercel, dan Linear). Antarmuka didesain fungsional, bersih, dan berwibawa untuk operator stasiun pengisian daya publik, memprioritaskan pemantauan keselamatan fisik, kejelasan durasi sewa, dan keterbacaan data listrik berdensitas tinggi tanpa ornamen AI Slop.

## Colors

- **Latar Belakang & Permukaan:**
  - `base` (`#0A0A0D`): Latar utama pekat, solid, doff (bukan biru/ungu neon).
  - `surface` (`#131318`): Kartu metrik dan panel instrumen utama.
  - `surface2` (`#181820`): Status aktif atau kontras elevasi kartu.
  - `hairline` (`#282832`): Garis batas pemisah tipis 1px yang bersih (*subtle border*).
- **Aksen Fungsional:**
  - `mint` (`#05DF72`): Mengindikasikan perangkat online, slot aktif, dan efisiensi normal.
  - `amber` (`#F59E0B`): Mengindikasikan slot menunggu pembayaran QRIS dan peringatan batas waktu.
  - `crimson` (`#EF4444`): Mengindikasikan tombol Emergency Shutdown dan kondisi bahaya kelistrikan/suhu.

## Typography

- **Teks Fungsional & Label:** Menggunakan font sans-serif modern (*Inter*) dengan hirarki tegas: Judul (18-20px semibold), Subjudul (14-16px medium), dan Label Metrik (11-12px medium, minimal 11px untuk aksesibilitas WCAG).
- **Angka Telemetri & Countdown Timer:** Wajib menggunakan font monospaced (*JetBrains Mono*) dengan bobot tegas (*bold/semibold*) untuk menjaga lebar digit angka tetap stabil saat detik countdown berjalan atau saat nilai Watt/Volt berfluktuasi.

## Layout

- Tata letak responsif dengan lebar kontainer maksimal 1400px.
- Struktur 3 blok vertikal teratur:
  1. Baris Atas: 4 Kartu Metrik Finansial & Omset.
  2. Bagian Tengah: 3 Kartu Relay Stop Kontak (Slot 1, Slot 2, Slot 3) dalam grid sejajar (*clean grid*, tanpa *nested card*).
  3. Bagian Bawah: Strip Telemetri Sensor Sensor Listrik (PZEM-004T) & Suhu Box (DHT22).

## Elevation & Depth

- **Tanpa Glassmorphism:** Menghindari efek blur kaca (`backdrop-blur`) berlebih.
- **Kedalaman Berbasis Garis (Hairline):** Menggunakan kontras permukaan solid (`#131318`) yang dipadukan dengan border 1px tipis (`#282832`) dan dot texture halus bergradien radial 1px untuk kedalaman visual yang elegan.

## Shapes

- Radius sudut terkontrol: `rounded-xl` (12px) untuk kartu metrik dan panel, serta `rounded-lg` (8px) untuk tombol dan badge status. Menghindari bentuk gelembung (*rounded-3xl*).

## Components

- **Header Status:** Teks ringkas tanpa dot kelap-kelip (`ESP32`, `Firebase`, tombol `Emergency Shutdown`).
- **Kartu Slot Stop Kontak:**
  - `STANDBY`: Warna netral tenang, waktu `--:--`, tombol matikan nonaktif.
  - `WAITING_PAYMENT`: Aksen amber lembut, countdown batas scan QRIS `MM:SS`.
  - `ACTIVE`: Aksen mint tegas, hitung mundur sisa waktu sewa, rincian durasi & tarif bayar, serta tombol `Matikan Manual` aktif.
- **Strip Sensor:** Grid 6 kolom dengan pembatas garis tipis (*divide-x*) menampilkan Tegangan (V), Arus (A), Daya (W), Energi (kWh), Suhu Box (°C), dan Kelembaban (%).

## Do's and Don'ts

- **DO:**
  - Selalu gunakan font monospaced untuk nilai angka dan timer.
  - Pertahankan kontras teks fungsional minimal `text-neutral-400` di atas latar belakang gelap.
  - Gunakan teks eksplisit untuk status sistem.
- **DON'T:**
  - JANGAN gunakan warna hijau default Tailwind `emerald` (`#10B981`); gunakan token `mint` (`#05DF72`).
  - JANGAN membungkus kartu di dalam kartu yang berdesakan (*nested cards*).
  - JANGAN gunakan teks fungsional di bawah ukuran 11px.
  - JANGAN gunakan gradasi teks pelangi atau bayangan warna-warni yang menyala norak.
