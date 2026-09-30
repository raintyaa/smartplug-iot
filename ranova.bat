@echo off
title RANOVA Smart Plug - Local Server
cls
echo ============================================================
echo   RANOVA Smart Plug - Local Server Launcher
echo ============================================================
echo.
echo [*] Memastikan lokasi project...
cd /d "%~dp0ranova-dashboard"

echo [*] Server URL : http://127.0.0.1:8000
echo [*] Catatan    : Pastikan MySQL (XAMPP) sudah aktif (Port 3306)
echo [*] Shortcut   : Tekan Ctrl+C untuk mematikan server
echo.
echo ============================================================
echo.

php artisan serve --port=8000

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [!] Gagal menjalankan server. Pastikan PHP sudah terinstall dan ada di PATH.
    pause
)
