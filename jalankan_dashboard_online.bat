@echo off
title RANOVA Smart Plug - Server & Cloudflare Online
color 0a

echo ============================================================
echo   RANOVA SMART PLUG - MEMULAI DASHBOARD & ONLINE TUNNEL
echo ============================================================
echo.
echo 1. Menjalankan Server Laravel Lokal (Port 8000)...
start "RANOVA Laravel Server" cmd /k "title RANOVA Laravel Server && cd /d "%~dp0ranova-dashboard" && php artisan serve --port=8000"

echo 2. Menyiapkan Terowongan Cloudflare...
if not exist "%~dp0cloudflared.exe" (
    echo [INFO] cloudflared.exe belum ada, mengunduh otomatis...
    powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe' -UseBasicParsing"
)

echo.
echo ============================================================
echo [PETUNJUK PENGGUNAAN]:
echo - Server lokal sudah otomatis menyala di jendela sebelah.
echo - Kredensial Login Operator:
echo     Username : rakha (atau rakha@ranova.id)
echo     Password : 02082006
echo.
echo Tunggu 3-5 detik sampai muncul baris:
echo   Your quick Tunnel has been created! Visit it at:
echo   https://xxxxxxxxxxxxxxxxxxxx.trycloudflare.com
echo.
echo Salin link https://...trycloudflare.com tersebut dan kirim ke WhatsApp Reza!
echo JANGAN TUTUP jendela ini selama Reza/Anda sedang menguji alat.
echo ============================================================
echo.

"%~dp0cloudflared.exe" tunnel --url http://127.0.0.1:8000

pause