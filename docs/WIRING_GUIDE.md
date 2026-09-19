# 🔌 Panduan Lengkap Wiring & Distribusi Daya — RANOVA Smart Plug IoT

Dokumen ini berisi panduan teknis perakitan kelistrikan, distribusi daya, skema pin-to-pin, serta solusi mengatasi keterbatasan jumlah pin daya pada mikrokontroler ESP32 di dalam box enclosure kayu RANOVA Smart Plug.

---

## 🛠️ Bagian 1: Komponen Tambahan untuk Mengatasi Keterbatasan Wiring

Untuk mengatasi jumlah pin GND dan 5V yang terbatas di ESP32 serta menghindari kabel yang ruwet/berseliweran di dalam kotak, siapkan komponen berikut:

| No | Komponen Tambahan | Estimasi Harga | Fungsi & Manfaat |
|---|---|---|---|
| 1 | **WAGO Connector 221-415** (Tipe Tuas 5 Lubang) — *Butuh 3–4 buah* | ~Rp 6.000 / pcs | **Kunci utama pemecah pin!** Cukup masukkan 1 kabel GND/5V, langsung bisa dicabang ke 4 komponen tanpa solder. |
| 2 | **ESP32 Expansion Shield 30-Pin** *(Opsional tapi SANGAT Direkomendasikan)* | ~Rp 20.000 | Papan dudukan ESP32 yang membuat **setiap pin GPIO punya pin 5V dan GND sendiri** lengkap dengan terminal baut. Sangat rapi! |
| 3 | **Heat Shrink Tube (Selongsong Bakar)** ukuran 2mm & 4mm | ~Rp 5.000 / meter | Membungkus kaki tombol metal agar kabel `+`, `-`, `NO`, `C` tidak saling bersentuhan/konslet di dalam box. |
| 4 | **Kabel Serabut AWG 22 / Kabel Pelangi (Ribbon Cable)** | ~Rp 10.000 / meter | Untuk memperpanjang sambungan tombol metal dan LCD ke ESP32 di dalam box. |
| 5 | **Terminal Block AC (Barrier Strip 4P/6P)** | ~Rp 8.000 | Untuk membagi listrik PLN AC 220V (Fasa, Netral, Arde) dengan aman. |

---

## ⚡ Bagian 2: Arsitektur Distribusi Daya DC (Sistem Common Ground & 5V)

Jangan tancapkan semua daya ke board ESP32. Gunakan konsep **Terminal Bus (WAGO Connector)** dari Power Supply 5V DC:

```text
               ┌──────────────────────────────┐
               │ Power Supply 5V DC (Adaptor) │
               └──────────────┬───────────────┘
                              │
              ┌───────────────┴───────────────┐
              │                               │
        Kabel [ +5V ]                   Kabel [ GND ]
              │                               │
        ┌─────▼──────┐                  ┌─────▼──────┐
        │  WAGO 5V   │                  │  WAGO GND  │
        │ (Bus +5V)  │                  │ (Bus GND)  │
        └──┬─┬─┬─┬─┬─┘                  └──┬─┬─┬─┬─┬─┘
           │ │ │ │ │                       │ │ │ │ │
           │ │ │ │ └─► VIN ESP32           │ │ │ │ └─► GND ESP32
           │ │ │ └───► VCC Relay 4-Ch      │ │ │ └───► GND Relay 4-Ch
           │ │ └─────► VCC LCD 16x2        │ │ └─────► GND LCD 16x2
           │ └───────► 5V PZEM-004T        │ └───────► GND PZEM-004T
           └─────────► VCC DHT22           └─────────► GND Semua Tombol & DHT22
```

---

## 🔌 Bagian 3: Tabel Lengkap Pemetaan Pin ESP32 (Seluruh Komponen)

Semua komponen logika terhubung ke ESP32 hanya membutuhkan **15 pin GPIO**:

### A. Tiga Tombol Metal 16mm (Input Saklar)
| Komponen | Kaki Tombol | Pin ESP32 | Keterangan |
|---|---|---|---|
| **Tombol 1 (Slot 1)** | `NO` | **`GPIO 27`** | Input Pull-up (Ditekan = LOW) |
| | `C / COM` | **WAGO GND** | Ground saklar |
| **Tombol 2 (Slot 2)** | `NO` | **`GPIO 14`** | Input Pull-up (Ditekan = LOW) |
| | `C / COM` | **WAGO GND** | Ground saklar |
| **Tombol 3 (Slot 3)** | `NO` | **`GPIO 12`** | Input Pull-up (Ditekan = LOW) |
| | `C / COM` | **WAGO GND** | Ground saklar |

### B. Tiga Lampu LED Ring Tombol (Output Indikator)
| Komponen | Kaki Tombol | Pin ESP32 | Keterangan |
|---|---|---|---|
| **LED Ring 1 (Slot 1)** | `+ (Anoda)` | **`GPIO 32`** | Kontrol Lampu Slot 1 (Kedip/Solid) |
| | `- (Katoda)` | **WAGO GND** | Ground lampu |
| **LED Ring 2 (Slot 2)** | `+ (Anoda)` | **`GPIO 33`** | Kontrol Lampu Slot 2 (Kedip/Solid) |
| | `- (Katoda)` | **WAGO GND** | Ground lampu |
| **LED Ring 3 (Slot 3)** | `+ (Anoda)` | **`GPIO 25`** | Kontrol Lampu Slot 3 (Kedip/Solid) |
| | `- (Katoda)` | **WAGO GND** | Ground lampu |

### C. Modul Relay 4-Channel (Sisi Kontrol DC)
| Pin Modul Relay | Terhubung Ke | Keterangan |
|---|---|---|
| `VCC` | **WAGO +5V** | Catu daya koil relay (wajib 5V stabil) |
| `GND` | **WAGO GND** | Ground kontrol optocoupler |
| `IN1` | **ESP32 GPIO 23** | Saklar Stop Kontak 1 (Active-LOW) |
| `IN2` | **ESP32 GPIO 19** | Saklar Stop Kontak 2 (Active-LOW) |
| `IN3` | **ESP32 GPIO 18** | Saklar Stop Kontak 3 (Active-LOW) |
| `IN4` | **ESP32 GPIO 26** | Cadangan / Spare |

### D. Sensor & Layar Tampilan
| Modul | Pin Modul | Terhubung Ke | Keterangan |
|---|---|---|---|
| **LCD 16x2 I2C** | `VCC` & `GND` | **WAGO +5V & WAGO GND** | Daya LCD & Backlight |
| | `SDA` | **ESP32 GPIO 21** | Jalur Data I2C |
| | `SCL` | **ESP32 GPIO 22** | Jalur Clock I2C |
| **DHT22 Suhu** | `VCC` & `GND` | **WAGO +5V & WAGO GND** | Daya Sensor Suhu |
| | `DATA / OUT` | **ESP32 GPIO 4** | Sinyal 1-Wire Digital |
| **PZEM-004T Daya**| `5V` & `GND` | **WAGO +5V & WAGO GND** | Daya Logika Optocoupler |
| | `TX` | **ESP32 GPIO 16 (RX2)** | Data dari sensor ke ESP32 |
| | `RX` | **ESP32 GPIO 17 (TX2)** | Perintah dari ESP32 ke sensor |

---

## 🪢 Bagian 4: Trik Wiring Estafet (Daisy-Chain) Khusus 3 Tombol Metal

Di balik panel depan tempat 3 tombol terpasang, ada 6 kaki yang butuh GND (`C` dan `-` dari 3 tombol). **Jangan tarik 6 kabel ke belakang box!**

Cukup sambungkan kabel secara **estafet paralel** antar tombol:

```text
Tombol 1:  Kaki [ - ] ─── sambung ke ─── Kaki [ C ]
                            │
                      (kabel jumper)
                            │
Tombol 2:  Kaki [ - ] ─── sambung ke ─── Kaki [ C ]
                            │
                      (kabel jumper)
                            │
Tombol 3:  Kaki [ - ] ─── sambung ke ─── Kaki [ C ] ───► 1 KABEL TUNGGAL ke WAGO GND!
```

> **Hasilnya:** Dari 3 tombol metal, kamu hanya perlu menarik **1 kabel GND saja** ke sirkuit utama. Sangat bersih dan hemat kabel!

---

## ⚡ Bagian 5: Wiring Listrik AC 220V (PLN ➔ Relay ➔ Stop Kontak Broco)

Kabel AC menggunakan **NYMHY 3x1.5mm²**:

### 1. Jalur Netral (Kabel Biru PLN):
Masuk ke terminal WAGO AC Netral, lalu dicabangkan langsung ke:
* Lubang Netral Stop Kontak Broco 1
* Lubang Netral Stop Kontak Broco 2
* Lubang Netral Stop Kontak Broco 3
* Input Netral (`N`) Power Supply 5V
* Terminal Netral (`N`) Sensor PZEM-004T

### 2. Jalur Arde / Ground (Kabel Kuning-Hijau PLN):
Dicabangkan ke semua pin Arde (besi jepit samping) pada ketiga Stop Kontak Broco.

### 3. Jalur Fasa / Setrum (Kabel Coklat PLN):
* Dari colokan PLN ➔ Masuk ke **Fuse Holder (Sekring 5A/10A)**.
* Keluar dari sekring ➔ Melewati lubang donat **Koil CT Sensor PZEM**.
* Setelah menembus CT ➔ Masuk ke terminal sekrup **`COM` Channel 1, 2, dan 3 Relay**.
* Terminal **`NO` (Normally Open)** masing-masing channel relay menuju ke lubang Fasa stop kontak:
  * `NO Channel 1` ➔ Lubang Fasa Stop Kontak Broco 1
  * `NO Channel 2` ➔ Lubang Fasa Stop Kontak Broco 2
  * `NO Channel 3` ➔ Lubang Fasa Stop Kontak Broco 3

---

## ⚠️ Bagian 6: Catatan Kritis & Troubleshooting Hasil Pengujian Hardware

1. **Wajib Tutup / Isolasi Pin `NC` Tombol Metal:**
   * Kaki `NC` bersebelahan langsung dengan kaki `NO`. Jika ujung jumper `NO` menyentuh pin `NC`, pin input ESP32 akan langsung terhubung ke Ground terus-menerus (*short circuit*) sehingga tombol terbaca sedang ditekan tanpa henti.
   * **Solusi:** Tutup pin `NC` dengan selongsong bakar (*heat shrink*), isolasi listrik, atau tekuk pin `NC` menjauhi `NO`.
2. **Jangan Tertukar Pin 23 dan 32:**
   * `GPIO 23` adalah output saklar **Relay 1** (Active-LOW).
   * `GPIO 32` adalah output lampu **LED Ring Tombol 1**.
3. **Catu Daya Relay Wajib 5V:**
   * Koil mekanik relay 4-channel membutuhkan 5V stabil agar kontak saklar tertarik dengan kuat (bunyi "KLIK" tajam). Jangan gunakan pin 3.3V untuk menyuplai VCC modul relay.