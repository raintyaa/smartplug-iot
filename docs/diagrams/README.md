# 📊 Diagram Arsitektur & Logika Sistem RANOVA (Archify Interactive Diagrams)

Direktori ini berisi diagram interaktif profesional berbasis file HTML mandiri (*self-contained interactive HTML*) yang dibuat menggunakan **Archify** (`tt-a1i/archify`) untuk kebutuhan dokumentasi dan materi presentasi proyek **RANOVA Smart Plug IoT**.

---

## 📑 Daftar Diagram Interaktif

| File Diagram | Tipe Visualisasi | Deskripsi & Fokus Pembahasan |
|---|---|---|
| 🌐 [**`ranova-system-architecture.html`**](ranova-system-architecture.html) | **Architecture Diagram** | Pemetaan topologi menyeluruh: User Smartphone, Mayar QRIS Gateway, Vercel Serverless Webhook, Firebase RTDB, Mikrokontroler ESP32, Sensor PZEM-004T & DHT22, Modul Relay 4-Ch, Stop Kontak AC 220V, serta Operator Web Dashboard & MySQL. |
| ⚡ [**`ranova-payment-sequence.html`**](ranova-payment-sequence.html) | **Sequence Diagram** | Alur panggilan data berurutan (*step-by-step trace*): Penekanan tombol fisik slot -> Kunci Mutex -> Scan QRIS & Bayar -> Webhook HTTP POST -> Update Firebase ACTIVE -> Relay ON -> Aliran listrik & Streaming telemetri daya. |
| 🔄 [**`ranova-slot-lifecycle.html`**](ranova-slot-lifecycle.html) | **Lifecycle / State Machine** | Siklus transisi status slot: `STANDBY` -> `WAITING_PAYMENT` (300s timeout) -> `ACTIVE` -> `COMPLETED`, dilengkapi interupsi pembatalan (`CANCELLED`), penghentian awal (`FORCE_STOP`), serta proteksi suhu `EMERGENCY_STOP` (Overheat $\ge 60^\circ\text{C}$). |

---

## 🚀 Cara Membuka & Menggunakan Saat Presentasi

1. **Buka di Browser Apa Pun:**
   * Cukup **klik dua kali (*double-click*)** salah satu file `.html` di atas (misal `ranova-system-architecture.html`).
   * File ini sepenuhnya mandiri (*self-contained*), tidak membutuhkan koneksi internet, server lokal, ataupun dependensi eksternal.

2. **Fitur Interaktif Presentasi:**
   * **Mode Gelap / Terang (*Dark / Light Mode*):** Klik tombol tema di sudut kanan atas sesuai tampilan proyektor.
   * **Path Tracing (Alur Interaktif):** Klik salah satu kotak komponen (misal klik node `Mayar.id PG` atau `ESP32`) untuk otomatis menyorot seluruh jalur data yang terhubung.
   * **Export Gambar untuk Slide (PowerPoint / Canva):** Klik tombol **Export** di toolbar diagram untuk langsung mengunduh gambar dalam format **PNG**, **SVG**, atau **WebM**.

---

## 🛠️ Sumber Data JSON
Tiap diagram dikompilasi dari spesifikasi JSON terstruktur yang berada di direktori ini:
* `ranova-system-architecture.architecture.json`
* `ranova-payment-sequence.sequence.json`
* `ranova-slot-lifecycle.lifecycle.json`

Untuk mengompilasi ulang setelah mengedit JSON:
```bash
node C:\Users\User\.gemini\config\skills\archify\bin\archify.mjs render <tipe> <input.json> <output.html>
```
