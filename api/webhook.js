// ============================================================
// RANOVA Smart Plug - Mayar QRIS Webhook Handler
// Vercel Serverless Function (Full-MQTT + Firebase Arch)
// ============================================================
// Alur:
// 1. Mayar mengirim POST ke sini setiap pembayaran QRIS berhasil
// 2. Baca slot yang sedang menunggu pembayaran (dari Firebase/MQTT)
// 3. Publish perintah aktivasi "START" via MQTT langsung ke ESP32 (sub-detik)
// 4. Catat transaksi ke Firebase/database untuk histori keuangan
// ============================================================

const admin = require("firebase-admin");
const mqtt = require("mqtt");

// --- KONFIGURASI TARIF DEFAULT ---
// Rp 1.000 = 15 menit (900 detik)
const DETIK_PER_SERIBU = 15 * 60;

// --- KONFIGURASI BROKER MQTT (EMQX Cloud Serverless) ---
const MQTT_BROKER_URL = process.env.MQTT_BROKER_URL || "mqtts://o2f6eb8b.ala.asia-southeast1.emqxsl.com:8883";
const MQTT_OPTIONS = {
  username: process.env.MQTT_USER || "ranova_esp32",
  password: process.env.MQTT_PASSWORD || "02082006",
  connectTimeout: 5000,
  rejectUnauthorized: false,
};

// --- INISIALISASI FIREBASE ADMIN SDK ---
if (!admin.apps.length) {
  try {
    const serviceAccount = JSON.parse(process.env.FIREBASE_SERVICE_ACCOUNT);
    admin.initializeApp({
      credential: admin.credential.cert(serviceAccount),
      databaseURL: process.env.FIREBASE_DATABASE_URL,
    });
  } catch (err) {
    console.error("Firebase init error:", err.message);
  }
}

const db = admin.database();

/**
 * Helper untuk mem-publish perintah aktivasi ke ESP32 via EMQX Broker
 */
function publishMqttCommand(slotNumber, durationSec, amount) {
  return new Promise((resolve) => {
    try {
      const client = mqtt.connect(MQTT_BROKER_URL, MQTT_OPTIONS);
      const timeout = setTimeout(() => {
        console.warn("[MQTT] Timeout koneksi ke broker EMQX.");
        client.end(true);
        resolve(false);
      }, 4000);

      client.on("connect", () => {
        const payload = JSON.stringify({
          action: "START",
          slot: slotNumber,
          duration: durationSec,
          amount: amount,
          timestamp: Math.floor(Date.now() / 1000),
        });

        client.publish("ranova/device_001/command", payload, { qos: 1 }, (err) => {
          clearTimeout(timeout);
          client.end(false, () => {
            if (err) {
              console.error("[MQTT] Gagal publish command:", err.message);
              resolve(false);
            } else {
              console.log(`[MQTT OK] Perintah START terkirim ke Slot ${slotNumber} (Durasi: ${durationSec}s)!`);
              resolve(true);
            }
          });
        });
      });

      client.on("error", (err) => {
        console.error("[MQTT ERROR]:", err.message);
        clearTimeout(timeout);
        client.end(true);
        resolve(false);
      });
    } catch (e) {
      console.error("[MQTT EXCEPTION]:", e.message);
      resolve(false);
    }
  });
}

// --- MAIN HANDLER ---
module.exports = async (req, res) => {
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method Not Allowed" });
  }

  try {
    const payload = req.body;
    console.log("=== Mayar Webhook Diterima ===");
    console.log(JSON.stringify(payload, null, 2));

    // --- PARSING PAYLOAD MAYAR ---
    const event  = payload.event  || payload.type  || payload.eventType || "";
    const status = (payload.data?.status || payload.status || "").toLowerCase();
    const amount = parseInt(
      payload.data?.amount  ||
      payload.data?.total   ||
      payload.amount        ||
      payload.total         || 0,
      10
    );

    console.log(`Event: "${event}" | Status: "${status}" | Amount: Rp ${amount}`);

    // Abaikan event testing dari Mayar
    if (event === "testing") {
      return res.status(200).json({ message: "Testing event diabaikan" });
    }

    // Validasi pembayaran sukses
    const isPaymentSuccess =
      (status === "success" || status === "paid" || status === "completed") &&
      amount > 0;

    if (!isPaymentSuccess) {
      console.log(`Bukan pembayaran sukses. Event: "${event}", Status: "${status}", Amount: ${amount}`);
      return res.status(200).json({ message: "Bukan pembayaran sukses", event, status, amount });
    }

    if (amount <= 0) {
      console.log("Amount tidak valid:", amount);
      return res.status(200).json({ message: "Amount tidak valid" });
    }

    // --- HITUNG DURASI SEWA (DINAMIS DARI FIREBASE / DASHBOARD) ---
    let basePrice = 1000;
    let baseDurationSeconds = DETIK_PER_SERIBU; // default 900 detik (15 menit)

    try {
      const pricingSnap = await db.ref("config/pricing").once("value");
      const pricingConfig = pricingSnap.val();
      if (pricingConfig && pricingConfig.base_price > 0 && pricingConfig.base_duration_seconds > 0) {
        basePrice = parseInt(pricingConfig.base_price, 10);
        baseDurationSeconds = parseInt(pricingConfig.base_duration_seconds, 10);
        console.log(`Menggunakan tarif dinamis dari Dashboard: Rp ${basePrice} = ${baseDurationSeconds} detik (${baseDurationSeconds / 60} menit)`);
      }
    } catch (errConfig) {
      console.log("Menggunakan fallback tarif default:", errConfig.message);
    }

    const durasiDetik = Math.floor((amount / basePrice) * baseDurationSeconds);
    console.log(`Durasi akhir dihitung: ${durasiDetik} detik (${durasiDetik / 60} menit)`);

    // --- BACA SLOT YANG SEDANG MENUNGGU DARI FIREBASE ---
    const selectionSnap = await db.ref("system/active_selection").once("value");
    const selection = selectionSnap.val();

    console.log("Active selection di Firebase:", selection);

    let slotKey = null;
    if (selection && selection.status === "WAITING_PAYMENT" && selection.slot && selection.slot !== "none") {
      slotKey = selection.slot;
    } else {
      // Fallback: cari slot mana yang sedang berstatus WAITING_PAYMENT di Firebase
      for (const s of ["slot1", "slot2", "slot3"]) {
        const snap = await db.ref(s).once("value");
        const val = snap.val();
        if (val && val.status === "WAITING_PAYMENT") {
          slotKey = s;
          console.log(`Fallback mendeteksi ${s} berstatus WAITING_PAYMENT`);
          break;
        }
        const snap2 = await db.ref(`slots/${s}`).once("value");
        const val2 = snap2.val();
        if (val2 && val2.status === "WAITING_PAYMENT") {
          slotKey = s;
          console.log(`Fallback mendeteksi slots/${s} berstatus WAITING_PAYMENT`);
          break;
        }
      }
    }

    if (!slotKey) {
      console.log("Tidak ada slot yang sedang menunggu pembayaran.");
      return res.status(200).json({
        message: "Tidak ada slot yang menunggu",
        selection,
      });
    }

    const slotNumber = parseInt(slotKey.replace("slot", ""), 10);

    // --- 1. PUBLISH PERINTAH START KE ESP32 VIA MQTT (SUB-DETIK) ---
    const mqttOk = await publishMqttCommand(slotNumber, durasiDetik, amount);

    // --- 2. SINKRONKAN DAN CATAT KE FIREBASE (AUDIT & HISTORI) ---
    const waktuAktif = Date.now();
    const slotUpdateData = {
      status:           "ACTIVE",
      duration_seconds: durasiDetik,
      active_duration:  durasiDetik,
      amount_paid:      amount,
      nominal_paid:     amount,
      activated_at:     waktuAktif,
      started_at:       Math.floor(waktuAktif / 1000),
      expires_at:       waktuAktif + durasiDetik * 1000,
    };

    await db.ref(`slots/${slotKey}`).update(slotUpdateData);
    await db.ref(`${slotKey}`).update(slotUpdateData);

    // Tambahkan ke histori transaksi
    await db.ref("transactions").push({
      slot:         slotKey,
      amount:       amount,
      duration_sec: durasiDetik,
      paid_at:      waktuAktif,
      event_raw:    payload.data?.id || payload.id || "unknown",
      delivery_mqtt: mqttOk,
    });

    // Reset active_selection ke IDLE
    await db.ref("system/active_selection").set({
      slot:      null,
      status:    "IDLE",
      timestamp: 0,
    });

    console.log(`✅ Slot ${slotKey} berhasil diaktifkan! Durasi: ${durasiDetik}s | MQTT Delivery: ${mqttOk}`);

    return res.status(200).json({
      success:          true,
      slot:             slotKey,
      slot_number:      slotNumber,
      amount:           amount,
      duration_seconds: durasiDetik,
      duration_minutes: durasiDetik / 60,
      mqtt_published:   mqttOk,
    });

  } catch (error) {
    console.error("❌ Webhook error:", error);
    return res.status(500).json({ error: "Internal server error", detail: error.message });
  }
};
