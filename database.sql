CREATE DATABASE IF NOT EXISTS simpro_himsika CHARACTER SET utf8mb4;
USE simpro_himsika;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(100),username VARCHAR(50) UNIQUE,password VARCHAR(255),role ENUM('anggota','keplak','admin'));
CREATE TABLE proker(id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(150),deskripsi TEXT,keplak_id INT NULL,tgl_usulan DATE NULL,tgl_final DATE NULL,
 status_tgl ENUM('belum','diajukan','disetujui','ditolak') DEFAULT 'belum',FOREIGN KEY(keplak_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE divisi(id INT AUTO_INCREMENT PRIMARY KEY,proker_id INT,nama VARCHAR(100),FOREIGN KEY(proker_id) REFERENCES proker(id) ON DELETE CASCADE);
CREATE TABLE pendaftaran(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,proker_id INT,divisi_id INT,acc_keplak TINYINT DEFAULT 0,acc_psdm TINYINT DEFAULT 0,
 UNIQUE(user_id,proker_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(proker_id) REFERENCES proker(id) ON DELETE CASCADE,FOREIGN KEY(divisi_id) REFERENCES divisi(id) ON DELETE CASCADE);
CREATE TABLE tupoksi(id INT AUTO_INCREMENT PRIMARY KEY,divisi_id INT,deskripsi VARCHAR(255),FOREIGN KEY(divisi_id) REFERENCES divisi(id) ON DELETE CASCADE);
CREATE TABLE tupoksi_progress(tupoksi_id INT,user_id INT,selesai TINYINT DEFAULT 0,PRIMARY KEY(tupoksi_id,user_id),FOREIGN KEY(tupoksi_id) REFERENCES tupoksi(id) ON DELETE CASCADE);
CREATE TABLE lpj_validasi(proker_id INT,user_id INT,PRIMARY KEY(proker_id,user_id),FOREIGN KEY(proker_id) REFERENCES proker(id) ON DELETE CASCADE);
CREATE TABLE evaluasi_form(id INT AUTO_INCREMENT PRIMARY KEY,judul VARCHAR(150),pertanyaan TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE evaluasi_jawaban(id INT AUTO_INCREMENT PRIMARY KEY,form_id INT,user_id INT,jawaban TEXT,UNIQUE(form_id,user_id),FOREIGN KEY(form_id) REFERENCES evaluasi_form(id) ON DELETE CASCADE);
-- Password semua akun demo: himsika123
INSERT INTO users(nama,username,password,role) VALUES
('Admin PSDM & SCBPH','admin','$2y$10$5XsZRXLBZ4cFvR7OAka9UugYXiZpx2FVhthR3PHxR.tBM/b69Jn5e','admin'),
('Budi Santoso (Keplak)','keplak','$2y$10$5XsZRXLBZ4cFvR7OAka9UugYXiZpx2FVhthR3PHxR.tBM/b69Jn5e','keplak'),
('Siti Aminah (Anggota)','anggota','$2y$10$5XsZRXLBZ4cFvR7OAka9UugYXiZpx2FVhthR3PHxR.tBM/b69Jn5e','anggota'),
('Rian Pratama (Anggota)','anggota2','$2y$10$5XsZRXLBZ4cFvR7OAka9UugYXiZpx2FVhthR3PHxR.tBM/b69Jn5e','anggota');
INSERT INTO proker(nama,deskripsi,keplak_id) VALUES('Seminar Nasional Sistem Informasi','Seminar teknologi tahunan Himsika',2),('Malam Keakraban (Makrab)','Makrab mahasiswa baru SI',NULL);
INSERT INTO divisi(proker_id,nama) VALUES(1,'Acara'),(1,'Humas & Sponsor'),(1,'Dokumentasi'),(2,'Acara'),(2,'Konsumsi');
INSERT INTO tupoksi(divisi_id,deskripsi) VALUES(1,'Menyusun rundown acara'),(1,'Koordinasi dengan pembicara'),(2,'Membuat proposal sponsor'),(3,'Dokumentasi foto & video');
INSERT INTO evaluasi_form(judul,pertanyaan) VALUES('Evaluasi Pengurus Semester Ganjil',"Bagaimana kinerja pengurus secara umum?\nApa kelebihan kepengurusan saat ini?\nApa saran perbaikan?");

CREATE TABLE IF NOT EXISTS agenda(id INT AUTO_INCREMENT PRIMARY KEY,proker_id INT,jenis VARCHAR(30),keterangan VARCHAR(255),tanggal DATE,status ENUM('diajukan','disetujui','ditolak') DEFAULT 'diajukan',FOREIGN KEY(proker_id) REFERENCES proker(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS notifikasi(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,pesan VARCHAR(255),link VARCHAR(30),dibaca TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
INSERT INTO agenda(proker_id,jenis,keterangan,tanggal,status) VALUES
(1,'Rapat','Rapat koordinasi panitia di ruang himpunan','2026-11-28','disetujui'),
(1,'Gladi','Gladi resik di aula','2026-12-10','diajukan'),
(1,'Hari H','Pelaksanaan Seminar Nasional','2026-12-12','disetujui');
