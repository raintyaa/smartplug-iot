# 🧪 Panduan Uji Koneksi MQTT EMQX Cloud (Tes 4)

Dokumen ini memandu pengujian konektivitas ESP32 ke **EMQX Cloud Serverless** menggunakan MQTTS (TLS Port 8883) dengan enkripsi aman.

---

## 📋 Parameter Koneksi
- **Broker Host:** `o2f6eb8b.ala.asia-southeast1.emqxsl.com`
- **Port TLS:** `8883`
- **Port WebSockets:** `8084`
- **Username:** `ranova_esp32`
- **Password:** *(Password yang dibuat di tab Authentication EMQX)*
- **Topik Telemetri:** `ranova/device_001/telemetry`
- **Topik Command:** `ranova/device_001/command`

---

## 🚀 Langkah Pengujian di Arduino IDE

1. Buka file sketch:
   `firmware/test/test_4_mqtt_emqx/test_4_mqtt_emqx.ino`
2. Pada baris 26, ganti `PASSWORD_ANDA` dengan password yang Anda buat di EMQX:
   ```cpp
   const char* MQTT_PASSWORD = "password_anda_disini";
   ```
3. Hubungkan ESP32 via kabel USB, pilih Port COM yang sesuai, lalu klik **Upload**.
4. Buka **Serial Monitor** pada baud rate **115200**.

---

## 🖥️ Cara Memantau Pesan Masuk di EMQX Cloud (Browser)

1. Buka console EMQX: [cloud-intl.emqx.com](https://cloud-intl.emqx.com)
2. Masuk ke deployment **`ranova-broker`**.
3. Di menu sebelah kiri, klik **Diagnostics** > **WebSocket Client** (atau tools test client bawaan).
4. Klik **Connect** (masukkan username `ranova_esp32` dan password).
5. Pada bagian **Subscription**, masukkan topik:
   `ranova/device_001/#`
   lalu klik **Subscribe**.
6. Anda akan melihat pesan telemetri JSON mengalir secara live setiap 3 detik langsung dari ESP32!
