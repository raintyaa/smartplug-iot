const admin = require("firebase-admin");

const DETIK_PER_SERIBU = 15 * 60;

const TIMEOUT_MENUNGGU_DETIK = 300;

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

module.exports = async (req, res) => {

  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method Not Allowed" });
  }

  try {
    const payload = req.body;
    console.log("=== Mayar Webhook Diterima ===");
    console.log(JSON.stringify(payload, null, 2));

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

    if (event === "testing") {
      return res.status(200).json({ message: "Testing event diabaikan" });
    }

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

    let basePrice = 1000;
    let baseDurationSeconds = DETIK_PER_SERIBU;

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

    const selectionSnap = await db.ref("system/active_selection").once("value");
    const selection = selectionSnap.val();

    console.log("Active selection di Firebase:", selection);

    let slotKey = null;
    if (selection && selection.status === "WAITING_PAYMENT" && selection.slot && selection.slot !== "none") {
      slotKey = selection.slot;
    } else {

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

    await db.ref("transactions").push({
      slot:         slotKey,
      amount:       amount,
      duration_sec: durasiDetik,
      paid_at:      waktuAktif,
      event_raw:    payload.data?.id || payload.id || "unknown",
    });

    await db.ref("system/active_selection").set({
      slot:      null,
      status:    "IDLE",
      timestamp: 0,
    });

    console.log(`✅ Slot ${slotKey} berhasil diaktifkan! Durasi: ${durasiDetik} detik`);

    return res.status(200).json({
      success:          true,
      slot:             slotKey,
      amount:           amount,
      duration_seconds: durasiDetik,
      duration_minutes: durasiDetik / 60,
    });

  } catch (error) {
    console.error("❌ Webhook error:", error);
    return res.status(500).json({ error: "Internal server error", detail: error.message });
  }
};
