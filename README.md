# SIMPRO Himsika (PHP + MySQL)
1. Install XAMPP/Laragon, aktifkan Apache & MySQL.
2. Salin folder ini ke htdocs, lalu impor `database.sql` lewat phpMyAdmin.
3. Sesuaikan kredensial di `config.php`.
4. Buka http://localhost/simpro-himsika
Akun demo (password himsika123): admin, keplak, anggota, anggota2.

## Update fitur agenda, kalender & notifikasi
- Instalasi baru: impor `database.sql` (sudah lengkap).
- Sudah punya database lama: jalankan `migrasi.sql` saja (tab SQL di phpMyAdmin), lalu timpa `index.php` dan `style.css`.
