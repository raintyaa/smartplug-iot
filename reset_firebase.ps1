# ============================================================
# RANOVA Smart Plug - Reset Firebase RTDB
# Jalankan script ini untuk membersihkan data uji di Firebase
# ============================================================
# Cara pakai:
#   Klik kanan file ini -> "Run with PowerShell"
#   atau jalankan di terminal: .\reset_firebase.ps1
# ============================================================

$FIREBASE_URL = "https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app"

Write-Host ""
Write-Host "=======================================================" -ForegroundColor Cyan
Write-Host "  RANOVA Smart Plug - Firebase Reset Tool" -ForegroundColor Cyan
Write-Host "=======================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Pilih aksi yang ingin dilakukan:" -ForegroundColor Yellow
Write-Host "  1. Hapus semua transaksi (/transactions)"
Write-Host "  2. Reset data sensor ke 0 (/sensors)"
Write-Host "  3. Keduanya (hapus transaksi + reset sensor)"
Write-Host "  4. Keluar"
Write-Host ""

$pilihan = Read-Host "Masukkan pilihan (1/2/3/4)"

switch ($pilihan) {
    "1" {
        Write-Host "`n[>>] Menghapus semua transaksi dari Firebase..." -ForegroundColor Yellow
        $result = Invoke-RestMethod -Uri "$FIREBASE_URL/transactions.json" -Method DELETE
        Write-Host "[OK] Transaksi berhasil dihapus!" -ForegroundColor Green
    }
    "2" {
        Write-Host "`n[>>] Mereset data sensor ke 0..." -ForegroundColor Yellow
        $body = '{"voltage":0.0,"current":0.0,"power":0.0,"energy":0.0,"temperature":0.0,"humidity":0.0,"updated_at":0}'
        $result = Invoke-RestMethod -Uri "$FIREBASE_URL/sensors.json" -Method PUT -ContentType "application/json" -Body $body
        Write-Host "[OK] Data sensor berhasil direset!" -ForegroundColor Green
    }
    "3" {
        Write-Host "`n[>>] Menghapus semua transaksi dari Firebase..." -ForegroundColor Yellow
        Invoke-RestMethod -Uri "$FIREBASE_URL/transactions.json" -Method DELETE | Out-Null
        Write-Host "[OK] Transaksi berhasil dihapus!" -ForegroundColor Green

        Write-Host "[>>] Mereset data sensor ke 0..." -ForegroundColor Yellow
        $body = '{"voltage":0.0,"current":0.0,"power":0.0,"energy":0.0,"temperature":0.0,"humidity":0.0,"updated_at":0}'
        Invoke-RestMethod -Uri "$FIREBASE_URL/sensors.json" -Method PUT -ContentType "application/json" -Body $body | Out-Null
        Write-Host "[OK] Data sensor berhasil direset!" -ForegroundColor Green
    }
    "4" {
        Write-Host "`nKeluar. Tidak ada perubahan yang dilakukan." -ForegroundColor Gray
        exit
    }
    default {
        Write-Host "`n[ERROR] Pilihan tidak valid." -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Selesai! Tekan Enter untuk menutup..." -ForegroundColor Cyan
Read-Host
