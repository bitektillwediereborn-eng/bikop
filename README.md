# 🌿 Koperasi Kantin – Wirausaha Bersama

Platform web terintegrasi untuk pengelolaan koperasi kantin dengan PHP + MySQL.

---

## 📁 Struktur File

```
koperasi/
├── config/
│   └── db.php              ← Konfigurasi koneksi database
├── includes/
│   ├── auth_check.php      ← Middleware autentikasi session
│   └── helpers.php         ← Helper functions (flash, format, badge)
├── index.php               ← Landing page + Form publik (Saran, Kebutuhan, Vote)
├── login.php               ← Halaman login internal
├── logout.php              ← Handler logout
├── dashboard.php           ← Dashboard admin (stats + ringkasan)
├── keuangan.php            ← CRUD Transaksi Keuangan
├── saran.php               ← Manajemen Saran (Read, Update Status, Catatan, Delete)
├── kebutuhan.php           ← Manajemen Kebutuhan (CRUD + Status)
├── export.php              ← Export laporan CSV (keuangan, saran, kebutuhan)
└── database.sql            ← Schema + seed data MySQL
```

---

## 🚀 Cara Setup

### 1. Import Database
```sql
-- Buka phpMyAdmin atau MySQL CLI
source /path/to/koperasi/database.sql;
-- Atau import via phpMyAdmin > Import > pilih database.sql
```

### 2. Konfigurasi Database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'koperasi_kantin');
define('DB_USER', 'root');      // ← sesuaikan
define('DB_PASS', '');          // ← sesuaikan
```

### 3. Jalankan di Web Server
- Taruh folder `koperasi/` di `htdocs/` (XAMPP) atau `www/` (WAMP/Laragon)
- Akses: `http://localhost/koperasi/`

---

## 🔑 Login Default

| Username | Password   | Role  |
|----------|------------|-------|
| admin    | password   | Admin |
| staff1   | password   | Staff |

> ⚠️ Segera ganti password setelah deploy menggunakan `password_hash('passwordbaru', PASSWORD_DEFAULT)` dan update di tabel `users`.

---

## ✨ Fitur yang Tersedia

### 🌐 Halaman Publik (`index.php`)
- Form **Saran & Masukan** – submit langsung ke database
- Form **Kelola Kebutuhan** – ajukan kebutuhan makanan/minuman  
- **Vote Menu** – 1 vote per IP per hari (anti double vote)
- Responsive design dengan animasi

### 🔒 Dashboard Internal (login required)
| Halaman | Fitur |
|---------|-------|
| `dashboard.php` | Statistik saldo, saran, kebutuhan, vote; ringkasan data terbaru |
| `keuangan.php` | **CRUD** transaksi: tambah, edit, hapus, validasi status, filter, export CSV |
| `saran.php` | Lihat detail, update status (baru → diproses → selesai), tambah catatan admin, delete, filter, cari |
| `kebutuhan.php` | **CRUD** kebutuhan: tambah manual, update status, catatan, delete, filter, cari |
| `export.php` | Export CSV untuk keuangan, saran, atau kebutuhan |

---

## 🗄️ Database Schema

| Tabel | Keterangan |
|-------|------------|
| `users` | Data pengguna internal (admin/staff) |
| `saran` | Saran & masukan dari taruna |
| `kebutuhan` | Pengajuan kebutuhan makanan/minuman |
| `transaksi` | Data keuangan pemasukan/pengeluaran |
| `menu` | Daftar menu kantin |
| `votes` | Vote menu favorit |

---

## 🔧 Requirements
- PHP >= 7.4 (disarankan PHP 8.x)
- MySQL >= 5.7 / MariaDB >= 10.3
- Web server: Apache/Nginx (XAMPP/WAMP/Laragon)
- Browser modern
