@echo off
title RANOVA Smart Plug - Launcher Console
color 0a

:MENU
cls
echo ============================================================
echo           RANOVA SMART PLUG - LAUNCHER CONSOLE
echo ============================================================
echo.
echo   [1] Jalankan Server Lokal Saja (Offline / Laptop)
echo   [2] Jalankan Server Online (Cloudflare Tunnel untuk Reza / HP)
echo   [3] Keluar
echo.
echo ============================================================
set /p PILIHAN="Pilih mode [1, 2, atau 3]: "

if "%PILIHAN%"=="1" goto MODE_LOKAL
if "%PILIHAN%"=="2" goto MODE_ONLINE
if "%PILIHAN%"=="3" exit /b
goto MENU

:MODE_LOKAL
cls
echo ============================================================
echo   MEMULAI SERVER LOKAL (OFFLINE)
echo ============================================================
echo [*] URL Browser : http://127.0.0.1:8000/login
echo [*] Catatan     : Pastikan MySQL (XAMPP) sudah aktif (Port 3306)
echo [*] Shortcut    : Tekan Ctrl+C untuk mematikan server
echo ============================================================
echo.
start http://127.0.0.1:8000/login
cd /d "%~dp0ranova-dashboard"
php artisan serve --port=8000
pause
goto MENU

:MODE_ONLINE
cls
echo ============================================================
echo   MEMULAI SERVER ONLINE (CLOUDFLARE TUNNEL)
echo ============================================================
echo.
echo [1/3] Menjalankan Server Laravel Lokal di Latar Belakang...
start "RANOVA Laravel Server" cmd /k "title RANOVA Laravel Server (Port 8000) && cd /d "%~dp0ranova-dashboard" && php artisan serve --port=8000"

echo [2/3] Memeriksa Program Cloudflare Tunnel...
if not exist "%~dp0cloudflared.exe" (
    echo [INFO] cloudflared.exe belum ditemukan. Mengunduh otomatis...
    powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe' -UseBasicParsing"
)

echo [3/3] Membuka Terowongan Cloudflare...
echo.
echo ============================================================
echo [PETUNJUK PENGGUNAAN]:
echo - Tunggu 3-5 detik sampai muncul baris teks:
echo     Your quick Tunnel has been created! Visit it at:
echo     https://xxxxxxxxxxxxxxxxxxxx.trycloudflare.com
echo.
echo - Salin link https://...trycloudflare.com tersebut dan kirim ke WhatsApp Reza!
echo - JANGAN TUTUP jendela ini selama Reza/Anda sedang menguji alat.
echo ============================================================
echo.

"%~dp0cloudflared.exe" tunnel --url http://127.0.0.1:8000
pause
goto MENU