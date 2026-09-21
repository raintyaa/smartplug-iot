# 🔌 Panduan Lengkap Wiring & Distribusi Daya — RANOVA Smart Plug IoT
*(Versi Final Lengkap dengan Power Supply 5V & Konektor WAGO — 21 September 2026)*

Dokumen ini adalah panduan teknis resmi perakitan kelistrikan, distribusi daya mandiri (internal switching power supply), skema alokasi port terminal WAGO, serta pemetaan pin-to-pin untuk seluruh modul hardware RANOVA Smart Plug.

---

## 🛠️ 1. Daftar Kebutuhan Konektor & Aksesoris Wiring

Untuk merakit seluruh sistem dengan rapi, aman dari korsleting, dan bebas dari pelintiran kabel berantakan di dalam box kayu, siapkan komponen penyambung berikut:

| No | Komponen Penyambung | Jumlah | Estimasi Biaya | Fungsi & Posisi Pemasangan |
|---|---|:---:|:---:|---|
| 1 | **WAGO Connector 221-415 (Tuas 5 Lubang)** | **8 – 9 pcs** | ~Rp 5.500 / pcs | **Terminal Bus Utama:**<br>• 2 pcs untuk AC Fasa (Pre-CT & Post-CT)<br>• 2 pcs untuk AC Netral (Jumper Bersama)<br>• 1 pcs untuk AC Arde / Grounding<br>• 2 pcs untuk DC +5V Bus (Jumper Bersama)<br>• 2 pcs untuk DC Ground Bus (Jumper Bersama) |
| 2 | **ESP32 Expansion Shield 30-Pin** *(Disarankan)* | **1 pcs** | ~Rp 18.000 | Dudukan ESP32 dengan baut terminal sekrup agar kabel GPIO tidak mudah lepas. |
| 3 | **Heat Shrink Tube (Selongsong Bakar) 2mm & 4mm** | **1 – 2 meter** | ~Rp 5.000 | Membungkus kaki tombol metal (`NO`, `C`, `+`, `-`) dan mengisolasi kaki `NC`. |
| 4 | **Kabel NYMHY 3x1.5mm² (Kabel Steker AC)** | Sesuai box | Standar SNI | Khusus mengalirkan listrik tegangan tinggi AC 220V PLN ke Stop Kontak Broco. |
| 5 | **Kabel Serabut AWG 22 / Kabel Pelangi Jumper** | 2 meter | ~Rp 8.000 | Khusus jalur tegangan rendah DC 5V, Ground, dan sinyal logika GPIO. |

---

## ⚡ 2. Arsitektur Distribusi Listrik AC 220V (PLN, Power Supply, & PZEM)

Sistem menggunakan kabel **NYMHY 3x1.5mm²**:
* **Coklat / Hitam:** Fasa (Line / Setrum 220V)
* **Biru:** Netral
* **Kuning / Kuning-Hijau:** Arde (Grounding Pengaman)

```text
                                [ LISTRIK AC 220V PLN ]
                                           │
         ┌─────────────────────────────────┼─────────────────────────────────┐
         │                                 │                                 │
   Kabel Biru (Netral)            Kabel Kuning (Arde)              Kabel Coklat (Fasa)
         │                                 │                                 │
         ▼                                 ▼                                 ▼
   [ WAGO AC-NETRAL ]               [ WAGO AC-ARDE ]                [ WAGO FASA 1 (Pre-CT) ]
   (5 Stop Kontak,                  (3 Stop Kontak Broco)            ├──► L Power Supply 5V
    PZEM, & PSU 5V)                                                  └──► Masuk ke Donat CT
                                                                             │
                                                                             ▼
                                                                    [ WAGO FASA 2 (Post-CT) ]
                                                                     ├──► L PZEM-004T
                                                                     └──► COM Relay 1, 2, 3
```

---

### A. Alokasi Port WAGO AC-Fasa (2 Unit WAGO 5-Lubang):
> 💡 **Trik Pemisahan Donat CT:** Input Fasa Power Supply diambil **SEBELUM** kabel melewati donat CT PZEM. Dengan cara ini, konsumsi listrik internal mikrokontroler & relay tidak terhitung sebagai beban sewa pengguna.

#### 1. WAGO AC-FASA 1 (Sebelum Donat CT / Jalur Sumber Utama):
* **Lubang 1:** Input Kabel Fasa (Coklat) dari Steker Colokan Dinding PLN
* **Lubang 2:** Input Kabel Fasa `L` Modul Power Supply 5V *(Daya untuk sistem)*
* **Lubang 3:** Kabel keluaran yang **dimasukkan menerobos lubang Cincin Donat CT PZEM**, lalu ujungnya masuk ke Lubang 1 WAGO AC-FASA 2
* **Lubang 4:** KOSONG / Cadangan
* **Lubang 5:** KOSONG / Cadangan

#### 2. WAGO AC-FASA 2 (Setelah Donat CT / Jalur Beban Sewa Terpantau):
* **Lubang 1:** Menerima Kabel Fasa dari WAGO Fasa 1 (yang telah melewati Donat CT)
* **Lubang 2:** Terminal Baut `L` Modul Sensor PZEM-004T *(untuk referensi voltase 220V)*
* **Lubang 3:** Terminal Baut `COM` Relay Channel 1
* **Lubang 4:** Terminal Baut `COM` Relay Channel 2
* **Lubang 5:** Terminal Baut `COM` Relay Channel 3

---

### B. Alokasi Port WAGO AC-Netral (2 Unit WAGO 5-Lubang Dijumper):
Karena ada 6 perangkat yang membutuhkan Netral AC (1 PLN, 3 Broco, 1 PZEM, 1 PSU), kita satukan 2 buah WAGO dengan 1 kabel jumper pendek:

#### 1. WAGO AC-NETRAL A:
* **Lubang 1:** Input Kabel Netral (Biru) dari Steker Colokan Dinding PLN
* **Lubang 2:** Kabel Jumper pendek ke Lubang 1 WAGO AC-NETRAL B
* **Lubang 3:** Kabel Netral menuju ke **Baut `N` Stop Kontak Broco 1**
* **Lubang 4:** Kabel Netral menuju ke **Baut `N` Stop Kontak Broco 2**
* **Lubang 5:** Kabel Netral menuju ke **Baut `N` Stop Kontak Broco 3**

#### 2. WAGO AC-NETRAL B:
* **Lubang 1:** Menerima Kabel Jumper dari WAGO AC-NETRAL A
* **Lubang 2:** Terminal Baut `N` Sensor Daya PZEM-004T
* **Lubang 3:** Input Kabel Netral `N` Modul Power Supply 5V
* **Lubang 4:** KOSONG / Cadangan
* **Lubang 5:** KOSONG / Cadangan

---

### C. Alokasi Port WAGO AC-Arde / Grounding (1 Unit WAGO 5-Lubang):
* **Lubang 1:** Input Kabel Arde (Kuning-Hijau) dari Steker PLN
* **Lubang 2:** Kabel Arde menuju ke **Baut Arde `⏚` Stop Kontak Broco 1**
* **Lubang 3:** Kabel Arde menuju ke **Baut Arde `⏚` Stop Kontak Broco 2**
* **Lubang 4:** Kabel Arde menuju ke **Baut Arde `⏚` Stop Kontak Broco 3**
* **Lubang 5:** KOSONG / Cadangan

---

### D. Jalur Saklar Relay ke Stop Kontak Broco (Langsung Baut-ke-Baut):
Kabel Fasa terkontrol dari relay mengalirkan listrik saat transaksi sewa aktif:
* Terminal Baut **`NO` Relay Channel 1** ──► Baut **`L` Stop Kontak Broco 1**
* Terminal Baut **`NO` Relay Channel 2** ──► Baut **`L` Stop Kontak Broco 2**
* Terminal Baut **`NO` Relay Channel 3** ──► Baut **`L` Stop Kontak Broco 3**

*(Baut `NC` pada ketiga channel relay dikosongkan).*

---

## 🔋 3. Arsitektur Distribusi Listrik DC (Output Power Supply 5V & Ground)

Modul Power Supply 5V internal bertindak sebagai "jantung" penyedia daya seluruh komponen DC mandiri di dalam kotak.

```text
                         ┌─────────────────────────────┐
                         │   Modul Power Supply 5V     │
                         │   (AC 220V ➔ DC 5V 2A/3A)   │
                         └──────────────┬──────────────┘
                                        │
                        ┌───────────────┴───────────────┐
                        │                               │
                  Kabel [ +5V ]                   Kabel [ GND ]
                        │                               │
                  ┌─────▼──────┐                  ┌─────▼──────┐
                  │ WAGO +5V A │                  │ WAGO GND A │
                  └─────┬──────┘                  └─────┬──────┘
                        │ (jumper)                      │ (jumper)
                  ┌─────▼──────┐                  ┌─────▼──────┐
                  │ WAGO +5V B │                  │ WAGO GND B │
                  └────────────┘                  └────────────┘
```

---

### A. Alokasi Port WAGO DC +5V (2 Unit WAGO 5-Lubang Dijumper):

#### 1. WAGO DC +5V-A (Pusat Suplai Daya):
* **Lubang 1:** Input kabel `+5V (V+)` dari Output Modul Power Supply
* **Lubang 2:** Kabel Jumper pendek ke Lubang 1 WAGO DC +5V-B
* **Lubang 3:** Pin **`VIN` (atau `5V`) Board ESP32** *(Menghidupkan mikrokontroler)*
* **Lubang 4:** Pin **`VCC` Modul Relay 4-Channel** *(Daya koil mekanik relay)*
* **Lubang 5:** Pin **`VCC` Sensor Suhu DHT22**

#### 2. WAGO DC +5V-B (Pusat Suplai Modul Tampilan & Telemetri):
* **Lubang 1:** Menerima Kabel Jumper dari WAGO DC +5V-A
* **Lubang 2:** Pin **`VCC` Layar LCD 16x2 I2C**
* **Lubang 3:** Pin **`5V` Modul Sensor Daya PZEM-004T**
* **Lubang 4:** KOSONG / Cadangan
* **Lubang 5:** KOSONG / Cadangan

---

### B. Alokasi Port WAGO DC Ground (2 Unit WAGO 5-Lubang Dijumper):

#### 1. WAGO DC GND-A (Ground Sensor & Logika):
* **Lubang 1:** Input kabel `GND (V-)` dari Output Modul Power Supply
* **Lubang 2:** Kabel Jumper pendek ke Lubang 1 WAGO DC GND-B
* **Lubang 3:** Pin **`GND` Board ESP32**
* **Lubang 4:** Pin **`GND` Sensor Suhu DHT22**
* **Lubang 5:** Pin **`GND` Layar LCD 16x2 I2C**

#### 2. WAGO DC GND-B (Ground Daya, Relay, & Tombol Panel):
* **Lubang 1:** Menerima Kabel Jumper dari WAGO DC GND-A
* **Lubang 2:** Pin **`GND` Modul Relay 4-Channel**
* **Lubang 3:** Pin **`GND` Modul Sensor Daya PZEM-004T**
* **Lubang 4:** **1 Kabel Tunggal Estafet Ground** dari 3 Tombol Metal Panel Depan
* **Lubang 5:** KOSONG / Cadangan

---

## 📌 4. Tabel Pemetaan Seluruh Pin GPIO ESP32

Semua kabel sinyal modul logika terhubung langsung ke pin ESP32 tanpa melewati WAGO:

| Modul Hardware | Kaki / Pin Modul | Pin ESP32 | Mode Firmware | Keterangan Fungsi |
|---|---|---|---|---|
| **Tombol 1 (Slot 1)** | `NO` | **GPIO 27** | `INPUT_PULLUP` | Saklar Pemilihan Slot 1 |
| **Tombol 2 (Slot 2)** | `NO` | **GPIO 14** | `INPUT_PULLUP` | Saklar Pemilihan Slot 2 |
| **Tombol 3 (Slot 3)** | `NO` | **GPIO 12** | `INPUT_PULLUP` | Saklar Pemilihan Slot 3 |
| **Semua Tombol** | `C (Common)` | WAGO GND-B (L-4) | Ground | Estafet kabel pendek antar-tombol |
| **LED Ring 1** | `+ (Anoda)` | **GPIO 32** | `OUTPUT` | Indikator Status Slot 1 (Kedip/Solid) |
| **LED Ring 2** | `+ (Anoda)` | **GPIO 33** | `OUTPUT` | Indikator Status Slot 2 (Kedip/Solid) |
| **LED Ring 3** | `+ (Anoda)` | **GPIO 25** | `OUTPUT` | Indikator Status Slot 3 (Kedip/Solid) |
| **Semua LED Ring** | `- (Katoda)` | WAGO GND-B (L-4) | Ground | Digabung ke kaki `C` masing-masing tombol |
| **Modul Relay** | `IN1` | **GPIO 23** | `OUTPUT` (Active-LOW) | Kontrol Aliran Listrik Broco 1 |
| | `IN2` | **GPIO 19** | `OUTPUT` (Active-LOW) | Kontrol Aliran Listrik Broco 2 |
| | `IN3` | **GPIO 18** | `OUTPUT` (Active-LOW) | Kontrol Aliran Listrik Broco 3 |
| | `IN4` | **GPIO 26** | `OUTPUT` | Cadangan |
| **Layar LCD 16x2** | `SDA` | **GPIO 21** | `I2C Data` | Komunikasi Tampilan Teks |
| | `SCL` | **GPIO 22** | `I2C Clock` | Clock Tampilan |
| **Sensor Suhu DHT22**| `DATA / OUT` | **GPIO 4** | `1-Wire Digital` | Pembacaan Suhu Internal Box |
| **Sensor PZEM-004T** | `TX` | **GPIO 16 (RX2)** | `Serial2 RX` | Menerima data $V, A, W, kWh$ |
| | `RX` | **GPIO 17 (TX2)** | `Serial2 TX` | Mengirim instruksi ke sensor |

---

## 🪢 5. Panduan Wiring Estafet (Daisy-Chain) 3 Tombol Metal

Di balik panel depan tempat 3 tombol terpasang, sambungkan kaki `C` dan `-` secara estafet kabel pendek:

```text
Tombol 1:  Kaki [ - ] ─── (sambung ke) ─── Kaki [ C ]
                             │
                      (kabel pendek 5 cm)
                             │
Tombol 2:  Kaki [ - ] ─── (sambung ke) ─── Kaki [ C ]
                             │
                      (kabel pendek 5 cm)
                             │
Tombol 3:  Kaki [ - ] ─── (sambung ke) ─── Kaki [ C ] ───► 1 KABEL TUNGGAL ke WAGO GND-B (Lubang 4)
```

> **Hasil:** Sangat rapi! Hanya ada **1 kabel Ground** yang melintang dari pintu depan box kayu menuju ke WAGO sirkuit belakang.

---

## ⚠️ 6. Peringatan Penting Perakitan & Keamanan

1. **Aturan Cincin Donat CT PZEM:**
   * Hanya **1 kabel Fasa (Coklat)** yang boleh melewati lubang donat CT.
   * **Dilarang keras** memasukkan kabel Fasa dan Netral bersamaan ke dalam donat CT, karena medan magnetnya akan saling meniadakan dan pembacaan arus menjadi 0 Ampere.
2. **Isolasi Kaki `NC` Tombol Metal:**
   * Kaki `NC` bersebelahan langsung dengan kaki `NO`. Wajib dibungkus dengan selongsong bakar (*heat shrink*) atau isolasi agar tidak menyentuh pin `NO`.
3. **Pemisahan Jalur AC dan DC:**
   * Jangan campur kabel AC 220V (NYMHY) dengan kabel jumper DC di dalam satu WAGO yang sama!
   * Rapikan kabel AC di bagian bawah box dan kabel sensor logika DC di bagian atas/tengah untuk meminimalisir interferensi elektromagnetik (*noise*).