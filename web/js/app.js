/**
 * RANOVA Smart Plug - Interactive Prototype Simulation
 * Catatan: Script ini adalah simulasi telemetri & alur kerja untuk keperluan demo,
 * pengujian UI/UX, dan verifikasi merchant Payment Gateway.
 */

// State simulasi slot stop kontak
const slotStates = {
    1: { active: true, secondsLeft: 1122, watt: 65.2, current: 0.31 },
    2: { active: false, secondsLeft: 0, watt: 0.0, current: 0.00 },
    3: { active: false, secondsLeft: 0, watt: 0.0, current: 0.00 }
};

// Format detik ke format mm:ss
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

// Simulasi Pergantian Status Slot Stop Kontak
function simulateToggle(slotNumber) {
    const slot = slotStates[slotNumber];
    const badge = document.getElementById(`badge-slot${slotNumber}`);
    const timer = document.getElementById(`timer-slot${slotNumber}`);
    const watt = document.getElementById(`watt-slot${slotNumber}`);
    const current = document.getElementById(`current-slot${slotNumber}`);
    const button = document.getElementById(`btn-slot${slotNumber}`);

    if (!slot.active) {
        // Aktifkan Slot (Simulasi Pembayaran Berhasil via QRIS)
        slot.active = true;
        slot.secondsLeft = 1800; // 30 Menit
        slot.watt = 48.5;
        slot.current = 0.22;

        badge.className = "px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
        badge.innerText = "AKTIF (TERBAYAR)";
        
        timer.innerText = formatTime(slot.secondsLeft);
        timer.className = "text-xl font-black text-white";
        
        watt.innerText = `${slot.watt} W`;
        current.innerText = `${slot.current} A`;
        
        button.innerText = "Nonaktifkan / Reset Slot";
        button.className = "mt-5 w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition";

        alert(`[SIMULASI WEBHOOK SUKSES]\n\nPembayaran QRIS Rp 2.000 terdeteksi untuk Slot ${slotNumber}!\nRelay aktif, stop kontak menyala selama 30 menit.`);
    } else {
        // Matikan Slot
        slot.active = false;
        slot.secondsLeft = 0;
        slot.watt = 0.0;
        slot.current = 0.00;

        badge.className = "px-3 py-1 rounded-full text-xs font-bold bg-slate-700 text-slate-400 border border-slate-600";
        badge.innerText = "STANDBY / MATI";
        
        timer.innerText = "00:00";
        timer.className = "text-xl font-black text-slate-500";
        
        watt.innerText = "0.0 W";
        current.innerText = "0.00 A";
        
        button.innerText = `Simulasi Pembayaran Slot ${slotNumber} (+Rp 2.000)`;
        button.className = "mt-5 w-full py-2.5 rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 text-xs font-semibold border border-indigo-500/30 transition";
    }
}

// Simulasi Countdown Timer Berjalan di Background
setInterval(() => {
    for (let i = 1; i <= 3; i++) {
        const slot = slotStates[i];
        if (slot.active && slot.secondsLeft > 0) {
            slot.secondsLeft--;
            const timer = document.getElementById(`timer-slot${i}`);
            if (timer) {
                timer.innerText = formatTime(slot.secondsLeft);
            }
            if (slot.secondsLeft <= 0) {
                // Auto shutdown saat waktu habis
                simulateToggle(i);
            }
        }
    }
}, 1000);
