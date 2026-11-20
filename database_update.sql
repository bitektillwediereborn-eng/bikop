-- ============================================
-- UPDATE DATABASE: Tambah tabel stok & barang
-- Jalankan ini di database koperasi_kantin
-- ============================================

USE koperasi_kantin;

-- ── Tabel Barang (Inventaris) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS barang (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kode         VARCHAR(20)  NOT NULL UNIQUE,
    nama         VARCHAR(150) NOT NULL,
    kategori     ENUM('makanan','minuman','snack','bahan_baku','peralatan','lainnya') NOT NULL DEFAULT 'makanan',
    satuan       VARCHAR(20)  NOT NULL DEFAULT 'pcs',
    stok_awal    INT          NOT NULL DEFAULT 0,
    stok_saat_ini INT         NOT NULL DEFAULT 0,
    stok_minimum INT          NOT NULL DEFAULT 5,
    harga_beli   DECIMAL(12,2) NULL,
    harga_jual   DECIMAL(12,2) NULL,
    keterangan   TEXT         NULL,
    aktif        TINYINT(1)   DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Tabel Gerakan Stok (Masuk / Keluar) ──────────────────────────
CREATE TABLE IF NOT EXISTS stok_gerakan (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    barang_id     INT          NOT NULL,
    tipe          ENUM('masuk','keluar') NOT NULL,
    jumlah        INT          NOT NULL,
    stok_sebelum  INT          NOT NULL DEFAULT 0,
    stok_sesudah  INT          NOT NULL DEFAULT 0,
    tanggal       DATE         NOT NULL,
    keterangan    VARCHAR(255) NULL,
    referensi     VARCHAR(100) NULL COMMENT 'No. faktur / ref transaksi',
    user_id       INT          NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)  ON DELETE SET NULL
);

-- ── Tambah kolom item_id ke transaksi (opsional, referensi barang) ─
ALTER TABLE transaksi
    ADD COLUMN IF NOT EXISTS item_id INT NULL AFTER kategori,
    ADD FOREIGN KEY (item_id) REFERENCES barang(id) ON DELETE SET NULL;

-- ── Seed Data Barang ──────────────────────────────────────────────
INSERT INTO barang (kode, nama, kategori, satuan, stok_awal, stok_saat_ini, stok_minimum, harga_beli, harga_jual) VALUES
('BRG-001', 'Nasi Putih',        'makanan',   'porsi', 100, 45,  20, 3000,  5000),
('BRG-002', 'Ayam Goreng',       'makanan',   'potong', 80, 30,  15, 8000, 15000),
('BRG-003', 'Mie Instan',        'makanan',   'bungkus', 200, 120, 30, 2500,  4000),
('BRG-004', 'Air Mineral 600ml', 'minuman',   'botol',  150, 60,  25, 2000,  3500),
('BRG-005', 'Teh Manis',         'minuman',   'gelas',  100, 40,  20, 1500,  3000),
('BRG-006', 'Kopi Susu',         'minuman',   'gelas',   80, 25,  15, 3000,  6000),
('BRG-007', 'Roti Tawar',        'snack',     'bungkus',  50, 12,  10, 8000, 12000),
('BRG-008', 'Pisang Goreng',     'snack',     'buah',   100, 55,  20, 1000,  2500),
('BRG-009', 'Beras 5kg',         'bahan_baku','karung',  30,  8,   5,60000, NULL),
('BRG-010', 'Minyak Goreng 2L',  'bahan_baku','botol',   20,  4,   5,28000, NULL);

-- ── Seed Gerakan Stok ─────────────────────────────────────────────
-- Stok masuk awal
INSERT INTO stok_gerakan (barang_id, tipe, jumlah, stok_sebelum, stok_sesudah, tanggal, keterangan) VALUES
(1,  'masuk', 100,   0, 100, '2025-01-01', 'Stok awal'),
(2,  'masuk',  80,   0,  80, '2025-01-01', 'Stok awal'),
(3,  'masuk', 200,   0, 200, '2025-01-01', 'Stok awal'),
(4,  'masuk', 150,   0, 150, '2025-01-01', 'Stok awal'),
(5,  'masuk', 100,   0, 100, '2025-01-01', 'Stok awal'),
(6,  'masuk',  80,   0,  80, '2025-01-01', 'Stok awal'),
(7,  'masuk',  50,   0,  50, '2025-01-01', 'Stok awal'),
(8,  'masuk', 100,   0, 100, '2025-01-01', 'Stok awal'),
(9,  'masuk',  30,   0,  30, '2025-01-01', 'Stok awal'),
(10, 'masuk',  20,   0,  20, '2025-01-01', 'Stok awal'),
-- Penjualan / keluar
(1,  'keluar', 55, 100, 45, '2025-01-05', 'Penjualan harian'),
(2,  'keluar', 50,  80, 30, '2025-01-05', 'Penjualan harian'),
(3,  'keluar', 80, 200,120, '2025-01-06', 'Penjualan harian'),
(4,  'keluar', 90, 150, 60, '2025-01-06', 'Penjualan harian'),
(5,  'keluar', 60, 100, 40, '2025-01-07', 'Penjualan harian'),
(6,  'keluar', 55,  80, 25, '2025-01-07', 'Penjualan harian'),
(7,  'keluar', 38,  50, 12, '2025-01-08', 'Penjualan harian'),
(8,  'keluar', 45, 100, 55, '2025-01-08', 'Penjualan harian'),
(9,  'keluar', 22,  30,  8, '2025-01-09', 'Pemakaian dapur'),
(10, 'keluar', 16,  20,  4, '2025-01-09', 'Pemakaian dapur');
