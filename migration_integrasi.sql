-- ============================================================
-- MIGRATION: Integrasi Transaksi ↔ Stok
-- Jalankan di database koperasi_kantin
-- ============================================================
USE koperasi_kantin;

-- Tambah kolom ke transaksi: link ke barang + jumlah unit
ALTER TABLE transaksi
    ADD COLUMN IF NOT EXISTS barang_id   INT  NULL AFTER kategori,
    ADD COLUMN IF NOT EXISTS jumlah_unit INT  NULL DEFAULT 0 AFTER barang_id;

-- Tambah kolom ke stok_gerakan: link ke transaksi (untuk rollback)
ALTER TABLE stok_gerakan
    ADD COLUMN IF NOT EXISTS transaksi_id INT NULL AFTER referensi;

-- Index untuk performa query join
CREATE INDEX IF NOT EXISTS idx_transaksi_barang   ON transaksi    (barang_id);
CREATE INDEX IF NOT EXISTS idx_gerakan_transaksi  ON stok_gerakan (transaksi_id);
