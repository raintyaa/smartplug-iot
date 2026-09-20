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
* **Hitam / Coklat:** Fasa (Line / Setrum 220V)
* **Biru:** Netral
* **Kuning / Kuning-Hijau:** Arde (Grounding Pengaman)

---

### 5.1 Panduan Merakit Kepala Steker Dutron DV-SAB-01 ke Kabel NYMHY
Jika kabel rol NYMHY belum memiliki kepala colokan, rakit steker Dutron dengan langkah berikut:
1. Buka sekrup pada bodi steker Dutron, pisahkan penutup plastiknya.
   > ⚠️ **PENTING:** Masukkan selongsong bodi plastik steker ke dalam kabel NYMHY terlebih dahulu sebelum memasang baut terminal!
2. Kupas kulit luar pembungkus kabel NYMHY sepanjang 3–4 cm, lalu kupas tembaga ketiga kawat sepanjang 0.8 cm.
3. Pasang kawat ke terminal steker:
   * **Kawat Kuning (Arde):** Pasang ke baut terminal **TENGAH** (yang menyambung ke plat besi jepit samping).
   * **Kawat Hitam/Coklat (Fasa):** Pasang ke salah satu baut **kaki tusuk kuningan bulat**.
   * **Kawat Biru (Netral):** Pasang ke baut **kaki tusuk kuningan bulat yang satunya lagi**.
4. Kencangkan klem penjepit leher kabel steker, lalu tutup dan sekrup kembali bodi luar steker.

---

### 5.2 Panduan Pengujian 1 Stop Kontak AC 220V (Tahap 3 - Single Socket Test) — `[TERUJI SUKSES 100% ✅]`
Skema ini telah diverifikasi menyalakan beban nyata (lampu meja/charger) secara otomatis saat transaksi sewa QRIS terkonfirmasi di cloud. Untuk menguji 1 Stop Kontak Broco Slot 1 secara terisolasi dan aman:

```text
Kabel Rol Steker PLN:
─────────────────────
Kabel Hitam (Fasa)   ───► Masuk ke baut [ COM ] Relay Channel 1
                                │
                          (saklar relay)
                                │
                              Baut [ NO ] Relay Channel 1
                                │
                        (kabel potongan pendek ~10-15 cm)
                                │
                                └─────────────────────► Masuk ke baut [ L ] Broco

Kabel Biru (Netral)  ─────────────────────────────────► Masuk ke baut [ N ] Broco
Kabel Kuning (Arde)  ─────────────────────────────────► Masuk ke baut [Arde] Broco
```

**4 Langkah Sambungan Baut Sederhana:**
1. **Relay ke Broco:** Hubungkan baut `NO` Relay 1 ke baut `L` Broco Slot 1 menggunakan 1 helai kabel pendek (~10–15 cm).
2. **Fasa PLN ke Relay:** Hubungkan kabel **Hitam/Coklat** dari kabel rol PLN ke baut `COM` Relay 1 (baut `NC` dikosongkan).
3. **Netral PLN ke Broco:** Hubungkan kabel **Biru** dari kabel rol PLN langsung ke baut `N` Broco Slot 1.
4. **Arde PLN ke Broco:** Hubungkan kabel **Kuning** dari kabel rol PLN langsung ke baut `Arde ⏚` Broco Slot 1.

---

### 5.3 Mengapa Rangkaian Multi-Stop Kontak Wajib PARALEL (Bukan Seri)?
Secara fisik, kabel jumper dipasang bersambung dari Stop Kontak 1 ke 2 ke 3 (*Daisy-Chain*), namun secara topologi kelistrikan rangkaian ini adalah **PARALEL MURNI**:
* **Keharusan Paralel:** Setiap stop kontak mendapatkan tegangan **220 Volt penuh** secara mandiri dari PLN. Jika Stop Kontak 1 mati atau dicabut, Stop Kontak 2 dan 3 tetap beroperasi normal.
* **Bahaya Jika Seri:** Jika dirangkai secara seri, tegangan 220V akan terbagi tiga ($220 \div 3 = 73\text{ Volt}$ per stop kontak) sehingga peralatan listrik tidak akan menyala, dan jika satu beban dicabut maka seluruh stop kontak akan mati bersamaan.

**Teknik Jumpering Paralel pada Terminal Baut:**
* Dua kawat (misal: Netral PLN dan Netral jumper ke stop kontak berikutnya) dipelintir bersama lalu **dijepit bersamaan dalam 1 lubang baut `N`**. Cara yang sama berlaku untuk lubang baut `Arde` dan `COM` Relay.
* Atau lebih rapi lagi, gunakan **WAGO Connector 5-Pin** sebagai terminal bus distribusi.

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
4. **Keamanan Pengujian AC Tanpa Modul Power Supply 5V Internal:**
   * Selama pengujian di meja kerja, ESP32 tetap aman dinyalakan via kabel USB laptop sementara beban 220V ditenagai dari steker dinding.
   * Modul relay memiliki **Optocoupler** (isolasi optik/cahaya dengan ketahanan isolasi hingga 2.500V RMS) yang memisahkan total rangkaian DC logika ESP32 dari tegangan tinggi AC 220V. Laptop dan ESP32 terlindungi 100% dari sengatan listrik AC.