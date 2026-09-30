# 🚀 Panduan Menjalankan Local Server RANOVA Smart Plug

Dokumen ini berisi panduan untuk menyalakan local server web dashboard Laravel, baik secara **manual**, menggunakan file launcher **`ranova.bat`**, maupun membuat shortcut kata kunci **`ranova`** agar bisa dipanggil dari mana saja di terminal.

---

## 📌 Prasyarat Awal (Penting!)
Sebelum menyalakan server Laravel, pastikan database lokal sudah menyala:
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **MySQL** (pastikan menyala hijau di port `3306`).
   *(Modul Apache di XAMPP tidak wajib dinyalakan, karena kita menggunakan server bawaan PHP/Artisan).*

---

## 🛠️ Cara 1: Menjalankan Secara Manual (Standar)

Jika kamu ingin menyalakan server melalui terminal biasa langkah demi langkah:

1. Buka **Terminal** / **PowerShell** / **Command Prompt**.
2. Arahkan direktori ke folder web dashboard:
   ```powershell
   cd "d:\Rakha\File Kuliah\Semester 5\IoT\Project\ranova-dashboard"
   ```
3. Jalankan server Laravel:
   ```bash
   php artisan serve
   ```
4. Buka browser dan akses alamat:
   👉 **`http://127.0.0.1:8000`** atau **`http://localhost:8000`**
5. Untuk mematikan server: tekan tombol **`Ctrl + C`** di terminal.

---

## ⚡ Cara 2: Menggunakan File Launcher `ranova.bat`

Di folder `Project`, sudah dibuatkan file siap pakai bernama **`ranova.bat`**.

* **Metode Klik (Tanpa Terminal):**
  * Cukup buka File Explorer ke folder `Project`, lalu **klik ganda (double-click)** pada file `ranova.bat`. Jendela server akan langsung terbuka dan aktif otomatis.
* **Metode Terminal di Folder Project:**
  * Buka terminal di folder `Project`, lalu cukup ketik:
    ```cmd
    .\ranova
    ```

---

## 🌟 Cara 3: Membuat Shortcut Global Keyword `ranova` di PowerShell (Bisa Dipanggil dari Mana Saja!)

Dengan cara ini, kamu bisa membuka PowerShell di direktori mana pun (misal baru buka laptop langsung buka PowerShell di desktop), lalu hanya mengetik satu kata **`ranova`**, server akan langsung berpindah folder dan menyala otomatis!

### 💡 Bagaimana Cara Kerjanya?
PowerShell memiliki file konfigurasi bernama **`$PROFILE`** yang otomatis dibaca dan dijalankan setiap kali PowerShell dibuka. Kita mendaftarkan sebuah *custom function* bernama `ranova` ke dalam file tersebut.

### 📝 Langkah-Langkah Pembuatannya:

1. **Buka PowerShell** di komputer kamu.
2. Ketik perintah berikut untuk membuka file profil PowerShell di Notepad:
   ```powershell
   notepad $PROFILE
   ```
   *(Jika muncul pesan peringatan bahwa file belum ada dan ditanya "Do you want to create a new file?", pilih **Yes**).*

3. **Salin & Tempel (Paste)** kode fungsi berikut ke dalam Notepad tersebut:
   ```powershell
   function ranova {
       Set-Location "d:\Rakha\File Kuliah\Semester 5\IoT\Project\ranova-dashboard"
       Write-Host "===================================================" -ForegroundColor Green
       Write-Host "  Menjalankan Local Server RANOVA Smart Plug...    " -ForegroundColor Cyan
       Write-Host "  URL: http://127.0.0.1:8000                       " -ForegroundColor Yellow
       Write-Host "  Tekan Ctrl+C untuk mematikan server             " -ForegroundColor DarkGray
       Write-Host "===================================================" -ForegroundColor Green
       php artisan serve --port=8000
   }
   ```

4. **Simpan file** (`Ctrl + S`), lalu tutup Notepad.
5. **Izinkan Eksekusi Script (Hanya dilakukan 1x seumur hidup):**
   Di jendela PowerShell, ketik perintah berikut lalu tekan Enter:
   ```powershell
   Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```
   *(Jika muncul pertanyaan konfirmasi, ketik `Y` lalu Enter).*

6. **Selesai! Sekarang Tes Kata Kuncinya:**
   * Tutup jendela PowerShell tadi, lalu buka PowerShell baru.
   * Cukup ketik:
     ```powershell
     ranova
     ```
   * Server langsung menyala di `http://127.0.0.1:8000`! 🎉

---

## 🌐 Cara 4 (Alternatif): Shortcut Kata Kunci di CMD / Run Dialog (Win + R)

Jika kamu lebih sering menggunakan **Command Prompt (CMD)** atau ingin bisa tekan **Windows + R** lalu ketik `ranova`:

1. Buka Start Menu, cari **"Edit the system environment variables"** (atau "Edit variabel lingkungan sistem") ➔ Tekan Enter.
2. Klik tombol **Environment Variables...** di pojok kanan bawah.
3. Pada bagian *User variables* (variabel untuk akunmu), cari variabel bernama **`Path`**, klik lalu tekan **Edit...**.
4. Klik tombol **New**, lalu masukkan path folder tempat file `ranova.bat` berada:
   ```text
   d:\Rakha\File Kuliah\Semester 5\IoT\Project
   ```
5. Klik **OK** pada semua jendela.
6. Sekarang, di CMD mana pun atau di jendela **Run (`Win + R`)**, kamu cukup ketik `ranova`, server akan otomatis berjalan!
