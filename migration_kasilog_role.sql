-- Tambah role kasilog (read-only monitoring)
USE koperasi_kantin;

ALTER TABLE users
    MODIFY role ENUM('admin','staff','kasilog') NOT NULL DEFAULT 'staff';
