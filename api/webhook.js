// ============================================================
// RANOVA Smart Plug - Mayar QRIS Webhook Handler
// Vercel Serverless Function
// ============================================================
// Alur:
// 1. Mayar mengirim POST ke sini setiap pembayaran QRIS berhasil
// 2. Kita baca slot mana yang sedang menunggu pembayaran di Firebase
// 3. Kita aktifkan slot tersebut sesuai nominal yang dibayar
// ============================================================

const admin = require("firebase-admin");

// --- KONFIGURASI ---
// Rp 1.000 = berapa detik durasi sewa?
// Default: Rp 1.000 = 15 menit (900 detik)
const DETIK_PER_SERIBU = 15 * 60; // 900 detik

// Timeout: berapa lama (detik) kita tunggu pembayaran setelah tombol ditekan?
const TIMEOUT_MENUNGGU_DETIK = 300; // 5 menit

// --- INISIALISASI FIREBASE ADMIN SDK ---
// Hanya inisialisasi sekali (penting untuk serverless/Vercel)
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

// --- MAIN HANDLER ---
module.exports = async (req, res) => {
  // Hanya terima POST request
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

    // Log lengkap untuk debugging
    console.log(`Event: "${event}" | Status: "${status}" | Amount: Rp ${amount}`);

    // Abaikan event testing dari Mayar
    if (event === "testing") {
      return res.status(200).json({ message: "Testing event diabaikan" });
    }

    // Proses jika status SUCCESS dan ada amount (berlaku untuk semua jenis event Mayar)
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

    // --- HITUNG DURASI SEWA ---
    const durasiDetik = Math.floor((amount / 1000) * DETIK_PER_SERIBU);
    console.log(`Durasi dihitung: ${durasiDetik} detik (${durasiDetik / 60} menit)`);

    // --- BACA SLOT YANG SEDANG MENUNGGU DARI FIREBASE ---
    const selectionSnap = await db.ref("system/active_selection").once("value");
    const selection = selectionSnap.val();

    console.log("Active selection di Firebase:", selection);

    if (!selection || selection.status !== "WAITING_PAYMENT" || !selection.slot || selection.slot === "none") {
      console.log("Tidak ada slot yang sedang menunggu pembayaran.");
      return res.status(200).json({
        message: "Tidak ada slot yang menunggu",
        selection,
      });
    }

    // Cek timeout — lewati jika timestamp = 0 (mode testing manual)
    const sekarang = Date.now();
    if (selection.timestamp > 0) {
      const selisihDetik = (sekarang - selection.timestamp) / 1000;
      if (selisihDetik > TIMEOUT_MENUNGGU_DETIK) {
        console.log(`Slot sudah timeout (${Math.round(selisihDetik)} detik yang lalu)`);
        await db.ref("system/active_selection").update({ status: "IDLE", slot: null });
        return res.status(200).json({ message: "Slot timeout, pembayaran terlambat" });
      }
    }

    const slotKey = selection.slot; // "slot1", "slot2", atau "slot3"

    // --- AKTIFKAN SLOT DI FIREBASE ---
    const waktuAktif = Date.now();
    await db.ref(`slots/${slotKey}`).update({
      status:           "ACTIVE",
      duration_seconds: durasiDetik,
      amount_paid:      amount,
      activated_at:     waktuAktif,
      expires_at:       waktuAktif + durasiDetik * 1000,
    });

    // Tambahkan ke histori transaksi
    await db.ref("transactions").push({
      slot:         slotKey,
      amount:       amount,
      duration_sec: durasiDetik,
      paid_at:      waktuAktif,
      event_raw:    payload.data?.id || payload.id || "unknown",
    });

    // Reset active_selection ke IDLE
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
