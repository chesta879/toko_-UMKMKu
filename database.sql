-- Database aplikasi pemesanan toko (berdasarkan ERD)
-- Import lewat phpMyAdmin (tab Import) atau: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS toko_pesanan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toko_pesanan;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detail_pesanan, pesanan, produk, kategori, pengguna, toko;
SET FOREIGN_KEY_CHECKS = 1;

-- Entitas: Toko
CREATE TABLE toko (
  id_toko      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_toko    VARCHAR(100) NOT NULL,
  alamat       VARCHAR(255) NOT NULL,
  kontak       VARCHAR(30)  NOT NULL,
  jam_buka     TIME NOT NULL DEFAULT '08:00:00',
  jam_tutup    TIME NOT NULL DEFAULT '21:00:00',
  status_buka  ENUM('buka','tutup') NOT NULL DEFAULT 'buka'
) ENGINE=InnoDB;

-- Entitas: Pengguna  (relasi "Memiliki": banyak pengguna -> 1 toko)
CREATE TABLE pengguna (
  id_pengguna INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_toko     INT UNSIGNED NOT NULL,
  nama        VARCHAR(100) NOT NULL,
  username    VARCHAR(40)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  hak_akses   ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  CONSTRAINT fk_pengguna_toko FOREIGN KEY (id_toko) REFERENCES toko(id_toko)
) ENGINE=InnoDB;

-- Entitas: Kategori
CREATE TABLE kategori (
  id_kategori   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Entitas: Produk  (relasi "Menjual": 1 toko -> banyak produk; "Mengelompokkan": 1 kategori -> banyak produk)
CREATE TABLE produk (
  id_produk   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_toko     INT UNSIGNED NOT NULL,
  id_kategori INT UNSIGNED NOT NULL,
  nama_produk VARCHAR(120) NOT NULL,
  gambar      VARCHAR(255) NULL,
  harga       INT UNSIGNED NOT NULL,
  stok        INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_produk_toko     FOREIGN KEY (id_toko)     REFERENCES toko(id_toko),
  CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori)
) ENGINE=InnoDB;

-- Entitas: Pesanan  (relasi "Menerima": 1 toko -> banyak pesanan; "Memproses": 1 pengguna -> banyak pesanan)
CREATE TABLE pesanan (
  id_pesanan     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_toko        INT UNSIGNED NOT NULL,
  id_pengguna    INT UNSIGNED NULL,
  nama_pelanggan VARCHAR(100) NOT NULL,
  total_harga    INT UNSIGNED NOT NULL DEFAULT 0,
  status_pesanan ENUM('baru','diproses','selesai','batal') NOT NULL DEFAULT 'baru',
  waktu_pesanan  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pesanan_status (id_toko, status_pesanan),
  CONSTRAINT fk_pesanan_toko     FOREIGN KEY (id_toko)     REFERENCES toko(id_toko),
  CONSTRAINT fk_pesanan_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Relasi "Memesan" (banyak-ke-banyak Pesanan <-> Produk), atribut: id_detail, jumlah, subtotal
CREATE TABLE detail_pesanan (
  id_detail  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT UNSIGNED NOT NULL,
  id_produk  INT UNSIGNED NOT NULL,
  jumlah     INT UNSIGNED NOT NULL,
  subtotal   INT UNSIGNED NOT NULL,
  CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE,
  CONSTRAINT fk_detail_produk  FOREIGN KEY (id_produk)  REFERENCES produk(id_produk)
) ENGINE=InnoDB;

-- ---------- Data contoh ----------
INSERT INTO toko (nama_toko, alamat, kontak, jam_buka, jam_tutup, status_buka) VALUES
 ('Toko Sumber Rejeki', 'Jl. Trunojoyo No. 12, Sumenep', '081234567890', '08:00', '21:00', 'buka');

-- Login awal: admin / admin123  dan  kasir / kasir123  (segera ganti setelah masuk)
INSERT INTO pengguna (id_toko, nama, username, password, hak_akses) VALUES
 (1, 'Administrator', 'admin', '$2y$10$yVBSoI4GUBB6eCkn6Ly3Nu6Ul.43bJEmzOr6EqfIXkHu5ekJLy1Sq', 'admin'),
 (1, 'Kasir Toko',    'kasir', '$2y$10$deRfMqOkJCVpvg3ob3XJ2ulBu9ksyq2EbLvYXGuIh3Untn0uHXTRu', 'kasir');

INSERT INTO kategori (nama_kategori) VALUES ('Makanan'), ('Minuman'), ('Kebutuhan Harian');

INSERT INTO produk (id_toko, id_kategori, nama_produk, harga, stok) VALUES
 (1, 1, 'Mie Instan Goreng', 3500, 100),
 (1, 1, 'Roti Tawar', 15000, 30),
 (1, 2, 'Air Mineral 600 ml', 4000, 80),
 (1, 2, 'Teh Botol', 5000, 60),
 (1, 3, 'Sabun Mandi', 4500, 40),
 (1, 3, 'Minyak Goreng 1 L', 18000, 25);
