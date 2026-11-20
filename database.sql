-- ============================================
-- DATABASE: Koperasi Kantin
-- ============================================

CREATE DATABASE IF NOT EXISTS koperasi_kantin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE koperasi_kantin;

-- ============================================
-- TABLE: users (admin/staff internal)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role        ENUM('admin','staff','kasilog') DEFAULT 'staff',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: saran (masukan dari taruna/publik)
-- ============================================
CREATE TABLE IF NOT EXISTS saran (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    nis_nip     VARCHAR(30)  NOT NULL,
    kategori    ENUM('menu','pelayanan','fasilitas','harga','kebersihan','lainnya') NOT NULL,
    isi_saran   TEXT         NOT NULL,
    status      ENUM('baru','diproses','selesai') DEFAULT 'baru',
    catatan_admin TEXT        NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: kebutuhan (permintaan makanan/minuman)
-- ============================================
CREATE TABLE IF NOT EXISTS kebutuhan (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nama                VARCHAR(100) NOT NULL,
    angkatan            VARCHAR(10)  NOT NULL,
    jenis               ENUM('makanan','minuman','snack','buah','lainnya') NOT NULL,
    item                VARCHAR(150) NOT NULL,
    jumlah              VARCHAR(50)  NOT NULL,
    tanggal_dibutuhkan  DATE         NOT NULL,
    status              ENUM('baru','diproses','dipenuhi','ditolak') DEFAULT 'baru',
    catatan_admin       TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: transaksi (keuangan koperasi)
-- ============================================
CREATE TABLE IF NOT EXISTS transaksi (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tanggal     DATE NOT NULL,
    deskripsi   VARCHAR(255) NOT NULL,
    tipe        ENUM('pemasukan','pengeluaran') NOT NULL,
    jumlah      DECIMAL(15,2) NOT NULL,
    kategori    VARCHAR(50) NULL,
    status      ENUM('pending','valid','ditolak') DEFAULT 'pending',
    user_id     INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- TABLE: menu (daftar menu kantin)
-- ============================================
CREATE TABLE IF NOT EXISTS menu (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu   VARCHAR(150) NOT NULL,
    deskripsi   TEXT NULL,
    harga       DECIMAL(10,2) NULL,
    aktif       TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: votes (vote menu favorit)
-- ============================================
CREATE TABLE IF NOT EXISTS votes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    menu_id         INT NOT NULL,
    voter_ip        VARCHAR(45) NULL,
    voter_session   VARCHAR(100) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE
);

-- ============================================
-- SEED DATA
-- ============================================

-- Default admin (password: admin123)
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Keuangan', 'staff');

-- Menu default
INSERT INTO menu (nama_menu, deskripsi, harga) VALUES
('Nasi Goreng Spesial', 'Nasi goreng dengan telur, ayam, dan sayuran', 15000),
('Mie Ayam Bakso', 'Mie ayam dengan bakso sapi pilihan', 12000),
('Soto Ayam', 'Soto ayam kuah bening dengan ketupat', 12000),
('Ayam Geprek', 'Ayam goreng krispi dengan sambal geprek', 18000);

-- Sample saran
INSERT INTO saran (nama, nis_nip, kategori, isi_saran, status) VALUES
('Ahmad Rizki', '2024001', 'menu', 'Mohon ditambahkan menu vegetarian untuk yang tidak makan daging', 'diproses'),
('Siti Nurhaliza', '2024025', 'pelayanan', 'Kecepatan pelayanan perlu ditingkatkan, terutama jam makan siang', 'selesai'),
('Budi Santoso', '2024050', 'fasilitas', 'Penambahan tempat duduk di area kantin sangat diperlukan', 'baru');

-- Sample kebutuhan
INSERT INTO kebutuhan (nama, angkatan, jenis, item, jumlah, tanggal_dibutuhkan, status) VALUES
('Andi Pratama', '2024', 'makanan', 'Nasi Goreng', '50 porsi', '2025-01-15', 'diproses'),
('Rina Sari', '2023', 'minuman', 'Jus Buah Segar', '30 cup', '2025-01-16', 'dipenuhi'),
('Deni Wijaya', '2024', 'snack', 'Roti Sandwich', '40 pcs', '2025-01-17', 'baru');

-- Sample transaksi
INSERT INTO transaksi (tanggal, deskripsi, tipe, jumlah, kategori, status) VALUES
('2025-01-01', 'Penjualan Makanan', 'pemasukan', 1500000, 'penjualan', 'valid'),
('2025-01-02', 'Pembelian Bahan Baku', 'pengeluaran', 800000, 'operasional', 'valid'),
('2025-01-03', 'Penjualan Minuman', 'pemasukan', 850000, 'penjualan', 'valid'),
('2025-01-04', 'Biaya Operasional', 'pengeluaran', 500000, 'operasional', 'pending'),
('2025-01-05', 'Penjualan Snack', 'pemasukan', 650000, 'penjualan', 'valid');

-- Sample votes
INSERT INTO votes (menu_id, voter_ip) VALUES
(1,NULL),(1,NULL),(1,NULL),(1,NULL),(1,NULL),
(2,NULL),(2,NULL),(2,NULL),
(3,NULL),(3,NULL),
(4,NULL),(4,NULL),(4,NULL),(4,NULL);
