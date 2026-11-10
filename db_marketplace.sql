-- =========================================
-- DATABASE MARKETPLACE (Soal 11)
-- Import melalui phpMyAdmin -> Import -> pilih file ini
-- =========================================

CREATE DATABASE IF NOT EXISTS marketplace_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE marketplace_db;

-- Hapus tabel lama supaya file ini bisa di-import berulang kali
DROP TABLE IF EXISTS detail_pesanan;
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS detail_keranjang;
DROP TABLE IF EXISTS keranjang;
DROP TABLE IF EXISTS produk;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS users;

-- =========================================
-- 1. TABEL USERS
-- Menyimpan data pembeli, penjual, dan admin
-- Password disimpan dalam bentuk password_hash()
-- status: untuk toko (role penjual) — 'nonaktif' membuat
--         seluruh produk tokonya tidak tampil di beranda
-- =========================================

CREATE TABLE users (
    id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20),
    alamat TEXT,
    role ENUM('pembeli', 'penjual', 'admin')
        NOT NULL DEFAULT 'pembeli',
    status ENUM('aktif', 'nonaktif')
        NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================
-- 2. TABEL KATEGORI
-- Menyimpan kategori produk
-- =========================================

CREATE TABLE kategori (
    id_kategori INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL UNIQUE,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================
-- 3. TABEL PRODUK
-- Menyimpan produk yang dijual penjual
-- =========================================

CREATE TABLE produk (
    id_produk INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_penjual INT UNSIGNED NOT NULL,
    id_kategori INT UNSIGNED NOT NULL,
    nama_produk VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(12,2) NOT NULL,
    stok INT UNSIGNED NOT NULL DEFAULT 0,
    gambar VARCHAR(255),
    status ENUM('aktif', 'nonaktif')
        NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_produk_penjual
        FOREIGN KEY (id_penjual)
        REFERENCES users(id_user)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_produk_kategori
        FOREIGN KEY (id_kategori)
        REFERENCES kategori(id_kategori)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_produk_penjual (id_penjual),
    INDEX idx_produk_kategori (id_kategori)
) ENGINE=InnoDB;

-- =========================================
-- 4. TABEL KERANJANG
-- Menyimpan keranjang setiap pembeli
-- status 'aktif' = keranjang yang sedang dipakai
-- status 'checkout' = keranjang yang sudah dibayar
-- =========================================

CREATE TABLE keranjang (
    id_keranjang INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user INT UNSIGNED NOT NULL,
    status ENUM('aktif', 'checkout')
        NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_keranjang_user
        FOREIGN KEY (id_user)
        REFERENCES users(id_user)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    INDEX idx_keranjang_user (id_user)
) ENGINE=InnoDB;

-- =========================================
-- 5. TABEL DETAIL KERANJANG
-- Menyimpan produk di dalam keranjang
-- =========================================

CREATE TABLE detail_keranjang (
    id_detail_keranjang INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_keranjang INT UNSIGNED NOT NULL,
    id_produk INT UNSIGNED NOT NULL,
    jumlah INT UNSIGNED NOT NULL DEFAULT 1,

    CONSTRAINT fk_detail_keranjang
        FOREIGN KEY (id_keranjang)
        REFERENCES keranjang(id_keranjang)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_detail_produk
        FOREIGN KEY (id_produk)
        REFERENCES produk(id_produk)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT unique_produk_keranjang
        UNIQUE (id_keranjang, id_produk)
) ENGINE=InnoDB;

-- =========================================
-- 6. TABEL PESANAN
-- Menyimpan transaksi pemesanan pembeli
-- =========================================

CREATE TABLE pesanan (
    id_pesanan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user INT UNSIGNED NOT NULL,
    tanggal_pesanan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    alamat_pengiriman TEXT NOT NULL,
    total_harga DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Metode pembayaran dipilih pembeli saat checkout
    metode_pembayaran ENUM('transfer', 'ewallet', 'cod')
        NOT NULL DEFAULT 'transfer',
    status ENUM(
        'menunggu',
        'diproses',
        'dikirim',
        'selesai',
        'dibatalkan'
    ) NOT NULL DEFAULT 'menunggu',

    CONSTRAINT fk_pesanan_user
        FOREIGN KEY (id_user)
        REFERENCES users(id_user)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_pesanan_user (id_user),
    INDEX idx_pesanan_tanggal (tanggal_pesanan)
) ENGINE=InnoDB;

-- =========================================
-- 7. TABEL DETAIL PESANAN
-- Menyimpan rincian produk setiap pesanan
-- =========================================

CREATE TABLE detail_pesanan (
    id_detail_pesanan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pesanan INT UNSIGNED NOT NULL,
    id_produk INT UNSIGNED NOT NULL,
    jumlah INT UNSIGNED NOT NULL,
    harga_saat_pesan DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,

    CONSTRAINT fk_detail_pesanan
        FOREIGN KEY (id_pesanan)
        REFERENCES pesanan(id_pesanan)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_detail_pesanan_produk
        FOREIGN KEY (id_produk)
        REFERENCES produk(id_produk)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_detail_pesanan_produk (id_produk)
) ENGINE=InnoDB;

-- =========================================
-- DATA AWAL (agar aplikasi langsung bisa dicoba)
-- =========================================

-- Users. Password sudah di-hash dengan password_hash()
-- admin123 | penjual123 | pembeli123
INSERT INTO users (nama, email, password, no_hp, alamat, role) VALUES
('Administrator', 'admin@marketplace.test', '$2y$10$YsSpYIjIGXETI3iBUNIXPezWemNLcgcdTOwdw0uON6Gy4BNHz/RWe', '08111111111', 'Jl. Pusat No. 1 Jakarta', 'admin'),
('Budi Santoso', 'budi@marketplace.test', '$2y$10$rWwmY47kkTjPyDrIEDkFUud73xrUFZI08W87Z3CXIVgGkVKObeqH6', '081234567890', 'Jl. Merdeka No. 10 Yogyakarta', 'penjual'),
('Siti Aminah', 'siti@marketplace.test', '$2y$10$dPKE8MN6BnkvXZmNlMZptOuNysaAyJMri687QmmpefIhDhadWOvLy', '089876543210', 'Jl. Kenanga No. 5 Bandung', 'pembeli'),
('Andi Wijaya', 'andi@marketplace.test', '$2y$10$rWwmY47kkTjPyDrIEDkFUud73xrUFZI08W87Z3CXIVgGkVKObeqH6', '082222333444', 'Jl. Diponegoro No. 22 Surabaya', 'penjual');

-- Kategori
INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Elektronik', 'Peralatan elektronik dan gadget'),
('Fashion', 'Pakaian dan aksesori mode'),
('Rumah Tangga', 'Kebutuhan perlengkapan rumah'),
('Kesehatan', 'Produk kesehatan dan perawatan');

-- Produk (penjual id 2 = Budi, penjual id 4 = Andi)
INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok, gambar, status) VALUES
(2, 1, 'Laptop ASUS VivoBook 14', 'Laptop tipis dengan prosesor Core i5, RAM 8GB, SSD 512GB, cocok untuk kerja dan kuliah.', 7500000.00, 10, 'produk-1.png', 'aktif'),
(2, 1, 'HP Samsung Galaxy A55', 'Smartphone layar 6.6 inci, kamera 50 MP, baterai 5000 mAh.', 4300000.00, 15, 'produk-2.png', 'aktif'),
(2, 2, 'Kemeja Batik Pria', 'Kemeja batik lengan panjang ukuran M sampai XXL, bahan katun adem.', 175000.00, 30, 'produk-3.png', 'aktif'),
(4, 2, 'Sepatu Sneakers Putih', 'Sepatu kasual putih unisex, nyaman dipakai harian, ukuran 39-44.', 350000.00, 25, 'produk-4.png', 'aktif'),
(4, 3, 'Panci Stainless 24 cm', 'Panci stainless steel anti karat, cocok untuk masak sehari-hari.', 250000.00, 12, 'produk-5.png', 'aktif'),
(4, 4, 'Termometer Digital', 'Termometer digital cepat dan akurat untuk mengukur suhu tubuh.', 90000.00, 40, 'produk-6.png', 'aktif'),
(2, 1, 'Mouse Wireless Lama', 'Produk tidak lagi dijual (status nonaktif, tidak tampil di beranda).', 75000.00, 5, 'produk-7.png', 'nonaktif');

-- =========================================
-- SELESAI MEMBUAT DATABASE, RELASI, DAN DATA AWAL
-- =========================================
