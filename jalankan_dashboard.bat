@echo off
title RANOVA Smart Plug - Web Dashboard Server
color 0b
echo ============================================================
echo   RANOVA SMART PLUG - MEMULAI SERVER WEB DASHBOARD
echo ============================================================
echo.
echo 1. Membuka direktori ranova-dashboard...
cd /d "d:\Rakha\File Kuliah\Semester 5\IoT\Project\ranova-dashboard"

echo 2. Membuka browser ke http://127.0.0.1:8000/login ...
start http://127.0.0.1:8000/login

echo 3. Menjalankan Server Laravel (PHP 8.4)...
echo    Email Login: admin@ranova.id
echo    Password   : admin123
echo.
echo    [TIPS] Jangan tutup jendela ini selama menggunakan dashboard.
echo    Untuk mematikan server, cukup tutup jendela ini atau tekan Ctrl+C.
echo ============================================================
echo.

"C:\Users\User\.config\herd\bin\php84\php.exe" artisan serve --port=8000

pause
