@echo off
title RANOVA Smart Plug - Cloudflare Online Tunnel
color 0a
echo ============================================================
echo   RANOVA SMART PLUG - MEMBUAT LINK ONLINE (CLOUDFLARE)
echo ============================================================
echo.
echo 1. Memeriksa cloudflared.exe...
if not exist "%~dp0cloudflared.exe" (
    echo [INFO] cloudflared.exe belum ditemukan, mengunduh otomatis...
    powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe' -UseBasicParsing"
)

echo.
echo 2. Kredensial Login Operator:
echo    Username : rakha (atau rakha@ranova.id)
echo    Password : 02082006
echo.
echo 3. Sedang menghubungkan terowongan Cloudflare ke http://127.0.0.1:8000...
echo.
echo ============================================================
echo [PETUNJUK PENTING]:
echo Tunggu sekitar 3-5 detik sampai muncul kotak teks bertuliskan:
echo   Your quick Tunnel has been created! Visit it at:
echo   https://xxxxxxxxxxxxxxxxxxxx.trycloudflare.com
echo.
echo Salin link https://...trycloudflare.com tersebut,
echo lalu kirim ke WhatsApp Reza atau buka di browser HP Anda!
echo.
echo JANGAN TUTUP jendela ini selama Reza/Anda sedang menguji alat.
echo ============================================================
echo.

"%~dp0cloudflared.exe" tunnel --url http://127.0.0.1:8000

pause