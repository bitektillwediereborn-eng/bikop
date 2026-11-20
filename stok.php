<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/integrasi.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$canWrite = canWrite();
if ($action !== '') {
    blockWriteAction('stok.php');
}

/* ════════════════════════════════════════════════════════════════════
   CRUD BARANG
════════════════════════════════════════════════════════════════════ */
if ($action === 'add_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode  = strtoupper(trim($_POST['kode']  ?? ''));
    $nama  = trim($_POST['nama']  ?? '');
    $kat   = $_POST['kategori']   ?? 'lainnya';
    $sat   = trim($_POST['satuan'] ?? 'pcs');
    $sawal = (int)($_POST['stok_awal'] ?? 0);
    $smin  = (int)($_POST['stok_minimum'] ?? 5);
    $hbeli = $_POST['harga_beli'] !== '' ? (float)$_POST['harga_beli'] : null;
    $hjual = $_POST['harga_jual'] !== '' ? (float)$_POST['harga_jual'] : null;
    $ket   = trim($_POST['keterangan'] ?? '');

    if ($kode && $nama) {
        try {
            $pdo->prepare("INSERT INTO barang (kode,nama,kategori,satuan,stok_awal,stok_saat_ini,stok_minimum,harga_beli,harga_jual,keterangan) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$kode, $nama, $kat, $sat, $sawal, $sawal, $smin, $hbeli, $hjual, $ket ?: null]);
            $bid = (int)$pdo->lastInsertId();
            if ($sawal > 0) {
                catatGerakan($pdo, $bid, 'masuk', $sawal, date('Y-m-d'), 'Stok awal', null, null, $_SESSION['user_id']);
            }
            setFlash('success', "Barang \"$nama\" berhasil ditambahkan!");
        } catch (PDOException $e) {
            setFlash('error', 'Kode barang sudah ada atau terjadi kesalahan!');
        }
    } else {
        setFlash('error', 'Kode dan nama barang wajib diisi!');
    }
    header('Location: stok.php');
    exit;
}

/* BULK ADD BARANG */
if ($action === 'bulk_add_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $rows = $_POST['rows'] ?? [];
    $ok = 0;

    foreach ($rows as $r) {

        $kode = strtoupper(trim($r['kode'] ?? ''));
        $nama = trim($r['nama'] ?? '');

        if (!$kode || !$nama) continue;

        try {

            $pdo->prepare("
                INSERT INTO barang
                (kode,nama,kategori,satuan,stok_awal,stok_saat_ini,stok_minimum)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([
                $kode,
                $nama,
                $r['kategori'] ?? 'lainnya',
                $r['satuan'] ?? 'pcs',
                (int)($r['stok_awal'] ?? 0),
                (int)($r['stok_awal'] ?? 0),
                (int)($r['stok_minimum'] ?? 5)
            ]);

            $ok++;
        } catch (Throwable $e) {
        }
    }

    setFlash('success', "$ok barang berhasil ditambahkan!");
    header("Location: stok.php");
    exit;
}

if ($action === 'edit_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)$_POST['id'];
    $kode  = strtoupper(trim($_POST['kode']  ?? ''));
    $nama  = trim($_POST['nama']  ?? '');
    $kat   = $_POST['kategori']   ?? 'lainnya';
    $sat   = trim($_POST['satuan'] ?? 'pcs');
    $smin  = (int)($_POST['stok_minimum'] ?? 5);
    $hbeli = $_POST['harga_beli'] !== '' ? (float)$_POST['harga_beli'] : null;
    $hjual = $_POST['harga_jual'] !== '' ? (float)$_POST['harga_jual'] : null;
    $ket   = trim($_POST['keterangan'] ?? '');
    if ($id && $kode && $nama) {
        $pdo->prepare("UPDATE barang SET kode=?,nama=?,kategori=?,satuan=?,stok_minimum=?,harga_beli=?,harga_jual=?,keterangan=? WHERE id=?")
            ->execute([$kode, $nama, $kat, $sat, $smin, $hbeli, $hjual, $ket ?: null, $id]);
        setFlash('success', 'Data barang berhasil diperbarui!');
    } else {
        setFlash('error', 'Data tidak valid!');
    }
    header('Location: stok.php');
    exit;
}

/* BULK UPDATE STOK MINIMUM */
if ($action === 'bulk_update_min' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $ids = $_POST['ids'] ?? [];
    $min = (int)($_POST['stok_minimum'] ?? 0);

    if ($ids && $min >= 0) {

        $ph = implode(',', array_fill(0, count($ids), '?'));

        $pdo->prepare("UPDATE barang SET stok_minimum=? WHERE id IN ($ph)")
            ->execute(array_merge([$min], array_map('intval', $ids)));

        setFlash('success', count($ids) . " barang berhasil diupdate!");
    }

    header("Location: stok.php");
    exit;
}

if ($action === 'del_barang' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM barang WHERE id=?")->execute([(int)$_GET['id']]);
    setFlash('success', 'Barang berhasil dihapus!');
    header('Location: stok.php');
    exit;
}

/* BULK DELETE BARANG */
if ($action === 'bulk_delete_barang' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ok = 0;

    foreach ($ids as $id) {
        try {
            $pdo->prepare("DELETE FROM barang WHERE id=?")->execute([$id]);
            $ok++;
        } catch (Throwable $e) {
        }
    }

    setFlash('success', "$ok barang berhasil dihapus!");
    header("Location: stok.php");
    exit;
}

/* BULK BUAT TRANSAKSI KEUANGAN DARI BARANG TERPILIH */
if ($action === 'bulk_transaksi_stok' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids     = array_map('intval', $_POST['ids']    ?? []);
    $tipe    = $_POST['tipe']    ?? '';
    $unit    = (int)($_POST['unit']    ?? 1);
    $tgl     = $_POST['tanggal']  ?? date('Y-m-d');
    $kat     = $_POST['kategori'] ?? 'lainnya';
    $status  = in_array($_POST['status'] ?? '', ['pending','valid']) ? $_POST['status'] : 'pending';

    if ($ids && in_array($tipe, ['pemasukan','pengeluaran']) && $unit > 0) {
        $ok = 0;
        foreach ($ids as $bid) {
            $b = $pdo->prepare("SELECT * FROM barang WHERE id=?");
            $b->execute([$bid]);
            $barang = $b->fetch();
            if (!$barang) continue;

            $harga   = $tipe === 'pemasukan' ? (float)($barang['harga_jual'] ?? 0) : (float)($barang['harga_beli'] ?? 0);
            $nominal = $harga * $unit;
            if ($nominal <= 0) continue; // skip barang tanpa harga

            $tipeTrx = $tipe === 'pemasukan' ? 'keluar' : 'masuk'; // stok movement
            $desk    = ($tipe === 'pemasukan' ? 'Penjualan' : 'Pembelian') . ' ' . $barang['nama'];

            try {
                $pdo->beginTransaction();
                // Buat transaksi keuangan
                $pdo->prepare("INSERT INTO transaksi (tanggal,deskripsi,tipe,jumlah,kategori,barang_id,jumlah_unit,status,user_id) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$tgl, $desk, $tipe, $nominal, $kat, $bid, $unit, $status, $_SESSION['user_id']]);
                $trxId = (int)$pdo->lastInsertId();

                // Catat gerakan stok
                catatGerakan($pdo, $bid, $tipeTrx, $unit, $tgl,
                    ($tipe === 'pemasukan' ? 'Penjualan' : 'Pembelian') . ' dari bulk transaksi',
                    null, $trxId, $_SESSION['user_id']);

                $pdo->commit();
                $ok++;
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }
        setFlash('success', "$ok transaksi keuangan berhasil dibuat dan stok diperbarui!");
    } else {
        setFlash('error', 'Data tidak valid! Pastikan tipe dan jumlah unit terisi.');
    }
    header('Location: stok.php');
    exit;
}


if ($action === 'add_gerakan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid     = (int)$_POST['barang_id'];
    $tipe    = $_POST['tipe'] ?? '';
    $jml     = (int)($_POST['jumlah'] ?? 0);
    $tgl     = $_POST['tanggal'] ?? date('Y-m-d');
    $ket     = trim($_POST['keterangan'] ?? '');
    $ref     = trim($_POST['referensi']  ?? '');
    $buatTrx = !empty($_POST['buat_transaksi']); // checkbox di form

    if ($bid && in_array($tipe, ['masuk', 'keluar']) && $jml > 0) {
        $pdo->beginTransaction();
        try {
            $trxId = null;

            // ── Jika user minta buat transaksi keuangan otomatis ──────
            if ($buatTrx) {
                $b = $pdo->prepare("SELECT * FROM barang WHERE id=?");
                $b->execute([$bid]);
                $barang = $b->fetch();

                // masuk stok = pengeluaran keuangan (beli barang)
                // keluar stok = pemasukan keuangan (jual barang)
                $tipeTrx  = ($tipe === 'masuk') ? 'pengeluaran' : 'pemasukan';
                $harga    = ($tipe === 'masuk') ? ($barang['harga_beli'] ?? 0) : ($barang['harga_jual'] ?? 0);
                $nominal  = (float)($_POST['nominal_trx'] ?? ($harga * $jml));
                $kategori = ($tipe === 'masuk') ? 'pembelian' : 'penjualan';

                if ($nominal > 0) {
                    $pdo->prepare("INSERT INTO transaksi (tanggal,deskripsi,tipe,jumlah,kategori,barang_id,jumlah_unit,status,user_id) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([
                            $tgl,
                            ($ket ?: ($tipe === 'masuk' ? 'Pembelian' : 'Penjualan') . ' ' . $barang['nama']),
                            $tipeTrx,
                            $nominal,
                            $kategori,
                            $bid,
                            $jml,
                            'pending',
                            $_SESSION['user_id']
                        ]);
                    $trxId = (int)$pdo->lastInsertId();
                }
            }

            // ── Catat gerakan stok ────────────────────────────────────
            catatGerakan($pdo, $bid, $tipe, $jml, $tgl, $ket, $ref ?: null, $trxId, $_SESSION['user_id']);

            $pdo->commit();
            $msg = "Stok " . ($tipe === 'masuk' ? 'masuk' : 'keluar') . " $jml unit berhasil dicatat!";
            if ($trxId) $msg .= " Transaksi keuangan #$trxId dibuat otomatis (status: pending).";
            setFlash('success', $msg);
        } catch (Throwable $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal: ' . $e->getMessage());
        }
    } else {
        setFlash('error', 'Semua field wajib diisi dengan benar!');
    }
    header("Location: stok.php");
    exit;
}

if ($action === 'del_gerakan' && isset($_GET['id'])) {
    $g = $pdo->prepare("SELECT * FROM stok_gerakan WHERE id=?");
    $g->execute([(int)$_GET['id']]);
    $gr = $g->fetch();
    if ($gr) {
        $pdo->prepare("DELETE FROM stok_gerakan WHERE id=?")->execute([$gr['id']]);
        recalcStok($pdo, $gr['barang_id']);
        setFlash('success', 'Catatan gerakan dihapus dan stok direcalculate!');
    }
    header("Location: stok.php");
    exit;
}

/* ════════════════════════════════════════════════════════════════════
   FETCH DATA
════════════════════════════════════════════════════════════════════ */
// Barang list
$searchB   = trim($_GET['qb'] ?? '');
$filterKat = $_GET['kat']     ?? '';
$filterRendah = isset($_GET['rendah']) && $_GET['rendah'] === '1';
$whereB = [];
$paramsB = [];
if ($searchB) {
    $whereB[] = "(nama LIKE ? OR kode LIKE ?)";
    $paramsB[] = "%$searchB%";
    $paramsB[] = "%$searchB%";
}
if ($filterKat) {
    $whereB[] = "kategori=?";
    $paramsB[] = $filterKat;
}
if ($filterRendah) {
    $whereB[] = "stok_saat_ini <= stok_minimum AND aktif = 1";
}
$wSQL = $whereB ? 'WHERE ' . implode(' AND ', $whereB) : '';
$stmtB = $pdo->prepare("SELECT * FROM barang $wSQL ORDER BY nama");
$stmtB->execute($paramsB);
$barangList = $stmtB->fetchAll();

// Gerakan terbaru 50 + join transaksi
$gerakan = $pdo->query("
    SELECT g.*, b.nama AS nama_barang, b.satuan,
           t.deskripsi AS trx_deskripsi, t.jumlah AS trx_nominal
    FROM stok_gerakan g
    JOIN barang b ON b.id = g.barang_id
    LEFT JOIN transaksi t ON t.id = g.transaksi_id
    ORDER BY g.created_at DESC
    LIMIT 60
")->fetchAll();

// Stats
$totalBarang  = $pdo->query("SELECT COUNT(*) FROM barang WHERE aktif=1")->fetchColumn();
$stokRendah   = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok_saat_ini<=stok_minimum AND aktif=1")->fetchColumn();
$totalMasuk   = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stok_gerakan WHERE tipe='masuk'")->fetchColumn();
$totalKeluar  = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stok_gerakan WHERE tipe='keluar'")->fetchColumn();
$nilaiStok    = $pdo->query("SELECT COALESCE(SUM(stok_saat_ini*harga_beli),0) FROM barang WHERE harga_beli IS NOT NULL AND aktif=1")->fetchColumn();
// Berapa gerakan yang link ke transaksi keuangan
$linkedTrx    = $pdo->query("SELECT COUNT(*) FROM stok_gerakan WHERE transaksi_id IS NOT NULL")->fetchColumn();

// Chart: masuk vs keluar per barang (top 10)
$chartData = $pdo->query("
    SELECT b.nama,
           SUM(CASE WHEN g.tipe='masuk'  THEN g.jumlah ELSE 0 END) AS masuk,
           SUM(CASE WHEN g.tipe='keluar' THEN g.jumlah ELSE 0 END) AS keluar
    FROM barang b
    LEFT JOIN stok_gerakan g ON g.barang_id=b.id
    WHERE b.aktif=1
    GROUP BY b.id ORDER BY keluar DESC LIMIT 10
")->fetchAll();

// Trend 30 hari
$trend = $pdo->query("
    SELECT DATE(created_at) AS tgl,
           SUM(CASE WHEN tipe='masuk'  THEN jumlah ELSE 0 END) AS masuk,
           SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) AS keluar
    FROM stok_gerakan
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl
")->fetchAll();

// Semua barang untuk dropdown gerakan
$allBarang = $pdo->query("SELECT id,nama,satuan,stok_saat_ini,harga_beli,harga_jual FROM barang WHERE aktif=1 ORDER BY nama")->fetchAll();

// Statistik per kategori
$katStats = $pdo->query("
    SELECT kategori,
           COUNT(*) jml,
           SUM(stok_saat_ini) total,
           SUM(CASE WHEN stok_saat_ini<=stok_minimum THEN 1 ELSE 0 END) rendah,
           SUM(stok_saat_ini*IFNULL(harga_beli,0)) nilai
    FROM barang WHERE aktif=1
    GROUP BY kategori ORDER BY total DESC
")->fetchAll();

// Top 5 barang paling banyak keluar
$top5 = $pdo->query("
    SELECT b.nama, b.kategori, b.stok_saat_ini, b.satuan, b.harga_jual,
           COALESCE(SUM(g.jumlah),0) tot
    FROM barang b
    LEFT JOIN stok_gerakan g ON g.barang_id=b.id AND g.tipe='keluar'
    WHERE b.aktif=1
    GROUP BY b.id ORDER BY tot DESC LIMIT 5
")->fetchAll();

$flash     = getFlash();
$activeTab = $_GET['tab'] ?? ($filterRendah ? 'barang' : 'barang');

?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #93c5fd;
            --secondary: #bfdbfe;
            --accent: #2563eb;
            --dark: #1a1a1a;
            --gray: #f3f4f6;
            --gray-dark: #e5e7eb;
            --text-gray: #6b7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, .1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            min-height: 100vh;
        }

        .topbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, .95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .topbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: .9rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
        }

        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
        }

        .tnav {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: .65rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all .25s;
            text-decoration: none;
            border: none;
            font-size: .85rem;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--accent);
        }

        .btn-secondary:hover {
            background: var(--primary);
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #dc2626;
            color: #fff;
        }

        .btn-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-blue:hover {
            background: #2563eb;
            color: #fff;
        }

        .btn-orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .btn-orange:hover {
            background: #ea580c;
            color: #fff;
        }

        .btn-sm {
            padding: .35rem .75rem;
            font-size: .78rem;
        }

        .main {
            padding: 6rem 2rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .ph {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .ph h2 {
            font-size: 1.8rem;
        }

        .integ-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 1.5px solid var(--accent);
            border-radius: 50px;
            padding: .3rem .85rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--accent);
        }

        /* Stats */
        .sg {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .sc {
            background: #fff;
            padding: 1rem 1.15rem;
            border-radius: 14px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .si {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .si.green {
            background: #dbeafe;
            color: var(--accent);
        }

        .si.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .si.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .si.orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .si.purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .si.teal {
            background: #ccfbf1;
            color: #0d9488;
        }

        .sc h4 {
            font-size: .7rem;
            color: var(--text-gray);
            margin-bottom: .1rem;
        }

        .sc p {
            font-size: 1rem;
            font-weight: 700;
        }

        /* Alert */
        .alert-low {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: .9rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .88rem;
            color: #92400e;
        }

        /* Nav tabs */
        .ntabs {
            display: flex;
            gap: .5rem;
            margin-bottom: 1.75rem;
            border-bottom: 2px solid var(--gray-dark);
            padding-bottom: .5rem;
            overflow-x: auto;
        }

        .ntab {
            padding: .65rem 1.2rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-gray);
            transition: all .25s;
            border-radius: 10px 10px 0 0;
            font-family: inherit;
            white-space: nowrap;
            font-size: .88rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .ntab.active {
            color: var(--accent);
            background: var(--secondary);
        }

        .ntab:hover {
            color: var(--accent);
        }

        .tpanel {
            display: none;
        }

        .tpanel.active {
            display: block;
        }

        /* Charts */
        .cg {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .cc {
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            min-width: 0; /* fix grid item overflow */
        }

        .cc h3 {
            font-size: .98rem;
            margin-bottom: 1.1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .chart-wrap {
            position: relative;
            height: 280px;
            width: 100%;
        }

        /* Filter */
        .fw {
            background: #fff;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .fr {
            display: flex;
            gap: .7rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .fr input,
        .fr select {
            padding: .6rem .9rem;
            border: 2px solid var(--gray-dark);
            border-radius: 10px;
            font-family: inherit;
            font-size: .85rem;
            background: #fff;
            transition: all .25s;
        }

        .fr input:focus,
        .fr select:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Card + Table */
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.1rem 1.5rem;
            background: var(--secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: .7rem;
        }

        .card-header h3 {
            font-size: .98rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .tw {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: .78rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-dark);
            font-size: .83rem;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
        }

        tr:hover {
            background: #fafafa;
        }

        .sb {
            padding: .2rem .65rem;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
        }

        .ss {
            background: #dbeafe;
            color: #2563eb;
        }

        .sw {
            background: #fef3c7;
            color: #d97706;
        }

        .sd {
            background: #fee2e2;
            color: #dc2626;
        }

        .acts {
            display: flex;
            gap: .3rem;
        }

        /* Stok bar */
        .sbw {
            display: flex;
            align-items: center;
            gap: .55rem;
            min-width: 110px;
        }

        .sbar {
            flex: 1;
            height: 8px;
            background: var(--gray-dark);
            border-radius: 50px;
            overflow: hidden;
        }

        .sbar-f {
            height: 100%;
            border-radius: 50px;
        }

        /* Link pill */
        .link-pill {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 50px;
            padding: .12rem .5rem;
            font-size: .7rem;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
            white-space: nowrap;
        }

        .link-pill:hover {
            background: #2563eb;
            color: #fff;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .mbox {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            width: 100%;
            max-width: 580px;
            animation: popIn .22s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(.91);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .mh {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .mh h3 {
            font-size: 1.15rem;
        }

        .mcl {
            background: none;
            border: none;
            cursor: pointer;
            padding: .4rem;
            border-radius: 50%;
        }

        .mcl:hover {
            background: var(--gray);
        }

        .fg {
            margin-bottom: .95rem;
        }

        .fg label {
            display: block;
            margin-bottom: .28rem;
            font-weight: 600;
            font-size: .84rem;
        }

        .fg input,
        .fg select,
        .fg textarea {
            width: 100%;
            padding: .72rem;
            border: 2px solid var(--gray-dark);
            border-radius: 10px;
            font-family: inherit;
            font-size: .88rem;
            transition: all .25s;
        }

        .fg input:focus,
        .fg select:focus,
        .fg textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
        }

        .fr2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        /* Integrasi section */
        .integ-section {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid var(--accent);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .integ-section h4 {
            font-size: .88rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: .7rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .toggle-row input[type=checkbox] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }

        .toggle-row label {
            font-weight: 600;
            font-size: .88rem;
            cursor: pointer;
        }

        /* Tipe radio */
        .tipe-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: .95rem;
        }

        .tipe-opt {
            border: 2px solid var(--gray-dark);
            border-radius: 12px;
            padding: .9rem;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            color: var(--text-gray);
            transition: all .25s;
        }

        .tipe-opt.active-masuk {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .tipe-opt.active-keluar {
            border-color: #dc2626;
            background: #fee2e2;
            color: #dc2626;
        }

        .tipe-opt input {
            display: none;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: .9rem 1.5rem;
            border-radius: 12px;
            color: #fff;
            font-weight: 500;
            z-index: 5000;
            animation: slideIn .3s;
            max-width: 420px;
            font-size: .88rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .2);
        }

        .toast.success {
            background: var(--accent);
        }

        .toast.error {
            background: #dc2626;
        }

        @keyframes slideIn {
            from {
                transform: translateX(110%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Bulk Bar */
        .bb-bar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff;
            padding: .85rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1rem;
            display: none;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 4px 12px rgba(30, 64, 175, .3);
        }
        .bb-bar.on { display: flex; }
        .bba { display: flex; gap: .45rem; flex-wrap: wrap; margin-left: auto; }
        .bbb { padding: .4rem .85rem; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: .3rem; transition: all .2s; }
        .bbb-ok { background: #dbeafe; color: #2563eb; } .bbb-ok:hover { background: #2563eb; color: #fff; }
        .bbb-del { background: #fee2e2; color: #dc2626; } .bbb-del:hover { background: #dc2626; color: #fff; }
        .bbb-trx { background: #dbeafe; color: #2563eb; } .bbb-trx:hover { background: #2563eb; color: #fff; }
        .bbb-cls { background: rgba(255,255,255,.2); color: #fff; } .bbb-cls:hover { background: rgba(255,255,255,.35); }
        tr.sel { background: #eff6ff !important; }
        .cbc { width: 40px; text-align: center; }
        input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); }

        /* Bulk Add table */
        .bat { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .bat th, .bat td { padding: .45rem .55rem; border-bottom: 1px solid var(--gray-dark); }
        .bat th { background: #f9fafb; font-weight: 600; white-space: nowrap; }
        .bat input, .bat select { width: 100%; padding: .38rem .5rem; border: 1.5px solid var(--gray-dark); border-radius: 7px; font-family: inherit; font-size: .82rem; }
        .bat input:focus, .bat select:focus { outline: none; border-color: var(--accent); }
        .bat .dr { background: none; border: none; cursor: pointer; padding: .25rem .4rem; border-radius: 6px; color: #dc2626; }
        .bat .dr:hover { background: #fee2e2; }
        .mbox.wide { max-width: 1000px; }

        @media(max-width:800px){ .sg{ grid-template-columns: repeat(3,1fr); } .cg{ grid-template-columns:1fr; } }
        @media(max-width:580px){ .sg{ grid-template-columns: repeat(2,1fr); } .fr2{ grid-template-columns:1fr; } }
        
/* ═══════════════════════════════════════
   RESPONSIVE – MOBILE FIRST
   Breakpoints: 480 | 640 | 768 | 1024
═══════════════════════════════════════ */

/* Hamburger button – only visible on mobile */
.mob-toggle{display:none;background:none;border:none;cursor:pointer;padding:.45rem;border-radius:8px;color:var(--accent,#2563eb);}
.mob-toggle:hover{background:#eff6ff;}
.mob-toggle svg{width:22px;height:22px;display:block;}

/* Mobile nav drawer */
.tnav{transition:all .25s;}

@media(max-width:768px){
  /* ── Topbar ── */
  .topbar-inner{padding:.75rem 1rem;}
  .logo span{font-size:1.1rem;}
  .mob-toggle{display:flex;align-items:center;justify-content:center;}
  .tnav{
    display:none;
    position:fixed;top:60px;left:0;right:0;
    background:#fff;
    flex-direction:column;
    padding:1rem;
    box-shadow:0 8px 24px rgba(0,0,0,.15);
    z-index:999;
    gap:.5rem;
    border-top:2px solid #dbeafe;
  }
  .tnav.open{display:flex;}
  .tnav .btn{width:100%;justify-content:flex-start;border-radius:10px;}

  /* ── Main padding ── */
  .main{padding:5rem 1rem 2rem!important;}

  /* ── Page header ── */
  .ph{flex-direction:column;align-items:flex-start!important;}
  .ph > div:last-child{width:100%;display:flex;flex-wrap:wrap;gap:.5rem;}
  .ph .btn{flex:1;min-width:120px;justify-content:center;}

  /* ── Stats grids ── */
  .sg,.stats-grid{grid-template-columns:repeat(2,1fr)!important;}
  .sc{padding:.85rem 1rem;}

  /* ── Filter bar ── */
  .fw{padding:.85rem 1rem;}
  .fr{flex-direction:column;gap:.5rem;}
  .fr input,.fr select{width:100%;}
  .fr .si2{min-width:unset;width:100%;}
  .chips{gap:.3rem;}

  /* ── Tables: horizontal scroll ── */
  .tw{overflow-x:auto;-webkit-overflow-scrolling:touch;}
  .tw table{min-width:600px;}
  table th,table td{padding:.6rem .75rem;font-size:.78rem;}

  /* ── Card header ── */
  .card-header{flex-direction:column;align-items:flex-start;gap:.5rem;}
  .card-header .btn{align-self:flex-start;}

  /* ── Bulk bar ── */
  .bb-bar{flex-direction:column;align-items:flex-start;}
  .bba{width:100%;flex-wrap:wrap;}

  /* ── Modals: full screen on mobile ── */
  .modal{align-items:flex-end;padding:0;}
  .mbox,.mbox.wide{
    max-width:100%!important;
    width:100%!important;
    border-radius:20px 20px 0 0;
    max-height:92vh;
    padding:1.5rem 1rem;
  }
  .fr2{grid-template-columns:1fr!important;}

  /* ── Chart grid ── */
  .cg{grid-template-columns:1fr!important;}

  /* ── Tab nav ── */
  .ntabs{gap:.25rem;}
  .ntab{padding:.55rem .85rem;font-size:.8rem;}

  /* ── Integ section ── */
  .integ-section{padding:.75rem 1rem;}

  /* ── Action buttons in table ── */
  .acts{gap:.2rem;flex-wrap:wrap;}

  /* ── Tipe radio grid ── */
  .tipe-grid{grid-template-columns:1fr 1fr;}

  /* ── Bulk add table wrapper ── */
  #bulkAddModal .mbox{padding:1rem .75rem;}
}

@media(max-width:480px){
  /* ── Single-col stats on tiny screens ── */
  .sg,.stats-grid{grid-template-columns:1fr!important;}

  /* ── Logo: hide text ── */
  .logo span{display:none;}

  /* ── Chips wrap tightly ── */
  .chip{font-size:.7rem;padding:.22rem .6rem;}

  /* ── Page title smaller ── */
  .ph h2{font-size:1.4rem;}

  /* ── Button text shorter ── */
  .btn{font-size:.8rem;padding:.55rem 1rem;}

  /* ── Table even more compact ── */
  table th,table td{padding:.5rem .6rem;font-size:.74rem;}
}

@media(min-width:769px) and (max-width:1024px){
  /* ── Medium screens: 3-col stats ── */
  .sg,.stats-grid{grid-template-columns:repeat(3,1fr)!important;}
  .main{padding:6rem 1.5rem 2.5rem!important;}
}

</style>
</head>

<body>

    <?php if ($flash): ?>
        <div class="toast <?= e($flash['type']) ?>" id="toast"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="topbar">
        <div class="topbar-inner">
            <a href="dashboard.php" class="logo">
                <img src="img/logostinkcl.png" alt="">
                <span>Koperasi Kantin</span>
            </a>
        <button class="mob-toggle" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
            <div class="tnav">
                <a href="dashboard.php" class="btn btn-secondary btn-sm"><i data-lucide="layout-dashboard" style="width:14px"></i> Dashboard</a>
                <a href="keuangan.php" class="btn btn-secondary btn-sm"><i data-lucide="wallet" style="width:14px"></i> Keuangan</a>
                <a href="saran.php" class="btn btn-secondary btn-sm"><i data-lucide="message-square" style="width:14px"></i> Saran</a>
                <a href="kebutuhan.php" class="btn btn-secondary btn-sm"><i data-lucide="shopping-cart" style="width:14px"></i> Kebutuhan</a>
                <?php require __DIR__ . '/includes/nav_account.php'; ?>
                <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin logout?')"><i data-lucide="log-out" style="width:14px"></i></a>
            </div>
        </div>
    </div>

    <div class="main">
        <?php require __DIR__ . '/includes/readonly_banner.php'; ?>
        <div class="ph">
            <div>
                <h2>Manajemen Stok Barang</h2>
                <div style="display:flex;align-items:center;gap:.75rem;margin-top:.3rem;flex-wrap:wrap;">
                    <p style="color:var(--text-gray);font-size:.9rem">Monitor inventaris & gerakan barang koperasi</p>
                    <span class="integ-badge"><i data-lucide="link" style="width:13px"></i> Terintegrasi dengan Keuangan</span>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <?php if ($canWrite): ?>
                <button class="btn btn-blue" onclick="openModal('gerakanModal')">
                    <i data-lucide="arrow-left-right" style="width:14px"></i> Catat Gerakan Stok
                </button>
                <button class="btn btn-orange" onclick="openBulkAddBarang()">
                    <i data-lucide="table-2" style="width:14px"></i> Bulk Tambah
                </button>
                <button class="btn btn-primary" onclick="openModal('addBarangModal')">
                    <i data-lucide="plus" style="width:14px"></i> Tambah Barang
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($stokRendah > 0): ?>
            <div class="alert-low">
                <i data-lucide="alert-triangle" style="width:20px;flex-shrink:0"></i>
                <strong><?= $stokRendah ?> barang</strong>&nbsp;stoknya di bawah minimum! Segera lakukan pembelian.
                <a href="stok.php?tab=barang&amp;rendah=1" class="btn btn-sm btn-orange" style="margin-left:auto">Lihat Detail</a>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="sg">
            <div class="sc">
                <div class="si green"><i data-lucide="package" style="width:20px"></i></div>
                <div>
                    <h4>Total Jenis Barang</h4>
                    <p><?= $totalBarang ?> item</p>
                </div>
            </div>
            <div class="sc">
                <div class="si blue"><i data-lucide="arrow-down-to-line" style="width:20px"></i></div>
                <div>
                    <h4>Total Masuk</h4>
                    <p><?= number_format($totalMasuk) ?> unit</p>
                </div>
            </div>
            <div class="sc">
                <div class="si orange"><i data-lucide="arrow-up-from-line" style="width:20px"></i></div>
                <div>
                    <h4>Total Keluar</h4>
                    <p><?= number_format($totalKeluar) ?> unit</p>
                </div>
            </div>
            <div class="sc">
                <div class="si red"><i data-lucide="alert-circle" style="width:20px"></i></div>
                <div>
                    <h4>Stok Rendah</h4>
                    <p style="color:#dc2626"><?= $stokRendah ?> item</p>
                </div>
            </div>
            <div class="sc">
                <div class="si purple"><i data-lucide="circle-dollar-sign" style="width:20px"></i></div>
                <div>
                    <h4>Nilai Stok</h4>
                    <p style="font-size:.88rem"><?= formatRupiah((float)$nilaiStok) ?></p>
                </div>
            </div>
            <div class="sc">
                <div class="si teal"><i data-lucide="link" style="width:20px"></i></div>
                <div>
                    <h4>Terhubung Transaksi</h4>
                    <p style="color:#0d9488"><?= $linkedTrx ?> gerakan</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="ntabs">
            <button class="ntab <?= $activeTab === 'barang' ? 'active' : '' ?>" onclick="showTab('barang')">
                <i data-lucide="package" style="width:15px"></i> Daftar Barang
            </button>
            <button class="ntab <?= $activeTab === 'gerakan' ? 'active' : '' ?>" onclick="showTab('gerakan')">
                <i data-lucide="arrow-left-right" style="width:15px"></i> Riwayat Gerakan
            </button>
            <button class="ntab <?= $activeTab === 'statistik' ? 'active' : '' ?>" onclick="showTab('statistik')">
                <i data-lucide="bar-chart-2" style="width:15px"></i> Statistik & Grafik
            </button>
        </div>

        <!-- ════ TAB: DAFTAR BARANG ════ -->
        <div class="tpanel <?= $activeTab === 'barang' ? 'active' : '' ?>" id="panel-barang">
            <div class="fw">
                <form method="GET"><input type="hidden" name="tab" value="barang">
                    <?php if ($filterRendah): ?><input type="hidden" name="rendah" value="1"><?php endif; ?>
                    <div class="fr">
                        <input type="text" name="qb" placeholder="🔍 Cari nama / kode barang..." value="<?= e($searchB) ?>" style="flex:1;min-width:180px">
                        <select name="kat">
                            <option value="">Semua Kategori</option>
                            <?php foreach (['makanan', 'minuman', 'snack', 'bahan_baku', 'peralatan', 'lainnya'] as $k): ?>
                                <option value="<?= $k ?>" <?= $filterKat === $k ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $k)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search" style="width:13px"></i> Cari</button>
                        <a href="stok.php?tab=barang" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </form>
                <?php if ($filterRendah): ?>
                <div style="margin-top:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;font-size:.85rem;color:#92400e;background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:.65rem 1rem;">
                    <i data-lucide="filter" style="width:15px;flex-shrink:0"></i>
                    <span>Menampilkan <strong><?= count($barangList) ?></strong> barang dengan stok di bawah minimum.</span>
                    <a href="stok.php?tab=barang" style="margin-left:auto;color:#b45309;font-weight:600;text-decoration:none;">Tampilkan semua barang →</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($canWrite): ?>
            <!-- Bulk Action Bar -->
            <div class="bb-bar" id="bbBar">
                <i data-lucide="check-square" style="width:18px;flex-shrink:0"></i>
                <span id="bbCount" style="font-weight:700">0 dipilih</span>
                <div class="bba">
                    <button class="bbb bbb-trx" onclick="openBulkTrx()"><i data-lucide="wallet" style="width:12px"></i> Buat Transaksi</button>
                    <button class="bbb bbb-ok" onclick="openBulkUpdateMin()"><i data-lucide="sliders" style="width:12px"></i> Update Stok Min</button>
                    <button class="bbb bbb-del" onclick="bulkDeleteBarang()"><i data-lucide="trash-2" style="width:12px"></i> Hapus Terpilih</button>
                    <button class="bbb bbb-cls" onclick="clearSel()">Batal Pilih</button>
                </div>
            </div>

            <!-- Hidden bulk forms -->
            <form id="formBulkUpdateMin" method="POST" action="stok.php" style="display:none">
                <input type="hidden" name="action" value="bulk_update_min">
                <input type="hidden" name="stok_minimum" id="bulkMinVal">
                <div id="buIds"></div>
            </form>
            <form id="formBulkTrx" method="POST" action="stok.php" style="display:none">
                <input type="hidden" name="action" value="bulk_transaksi_stok">
                <input type="hidden" name="tipe"     id="btTipe">
                <input type="hidden" name="unit"     id="btUnit">
                <input type="hidden" name="tanggal"  id="btTgl">
                <input type="hidden" name="kategori" id="btKat">
                <input type="hidden" name="status"   id="btStatus">
                <div id="btIds"></div>
            </form>
            <?php endif; ?>
            <div class="card">
                <div class="card-header">
                    <h3><i data-lucide="package" style="width:17px"></i> Data Barang
                        <span style="background:var(--accent);color:#fff;border-radius:50px;padding:.1rem .55rem;font-size:.72rem;margin-left:.25rem"><?= count($barangList) ?></span>
                        <?php if ($filterRendah): ?>
                        <span style="background:#f59e0b;color:#fff;border-radius:50px;padding:.1rem .55rem;font-size:.72rem;margin-left:.25rem">Stok Rendah</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($canWrite): ?><th class="cbc"><input type="checkbox" id="cbAll" onchange="selAll(this.checked)"></th><?php endif; ?>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Stok Saat Ini</th>
                                <th>Min</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Status</th>
                                <?php if ($canWrite): ?><th>Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($barangList)): ?>
                                <tr>
                                    <td colspan="<?= $canWrite ? 11 : 9 ?>" style="text-align:center;color:var(--text-gray);padding:2.5rem">
                                        <?= $filterRendah ? 'Tidak ada barang dengan stok di bawah minimum.' : 'Belum ada data barang.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($barangList as $b):
                                $pct      = $b['stok_minimum'] > 0 ? min(100, round($b['stok_saat_ini'] / ($b['stok_minimum'] * 3) * 100)) : 100;
                                $isLow    = $b['stok_saat_ini'] <= $b['stok_minimum'];
                                $barColor = $isLow ? '#dc2626' : ($pct < 50 ? '#d97706' : '#2563eb');
                            ?>
                                <tr <?= $isLow ? 'style="background:#fff5f5"' : '' ?>>
                                    <?php if ($canWrite): ?><td class="cbc"><input type="checkbox" class="rcb" value="<?= $b['id'] ?>" onchange="onCb()"></td><?php endif; ?>
                                    <td><code style="background:var(--gray);padding:.2rem .45rem;border-radius:5px;font-size:.78rem"><?= e($b['kode']) ?></code></td>
                                    <td><strong><?= e($b['nama']) ?></strong>
                                        <?php if ($b['keterangan']): ?><br><small style="color:var(--text-gray)"><?= e(substr($b['keterangan'], 0, 38)) ?></small><?php endif; ?>
                                    </td>
                                    <td><?= ucwords(str_replace('_', ' ', $b['kategori'])) ?></td>
                                    <td><?= e($b['satuan']) ?></td>
                                    <td>
                                        <div class="sbw">
                                            <div class="sbar">
                                                <div class="sbar-f" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                                            </div>
                                            <span style="font-weight:700;color:<?= $barColor ?>;font-size:.88rem"><?= $b['stok_saat_ini'] ?></span>
                                            <?php if ($isLow): ?><i data-lucide="alert-triangle" style="width:14px;color:#dc2626"></i><?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $b['stok_minimum'] ?></td>
                                    <td><?= $b['harga_beli'] ? formatRupiah((float)$b['harga_beli']) : '<span style="color:var(--text-gray)">–</span>' ?></td>
                                    <td><?= $b['harga_jual'] ? formatRupiah((float)$b['harga_jual']) : '<span style="color:var(--text-gray)">–</span>' ?></td>
                                    <td><?= $isLow ? '<span class="sb sd">Stok Rendah</span>' : '<span class="sb ss">Normal</span>' ?></td>
                                    <?php if ($canWrite): ?>
                                    <td>
                                        <div class="acts">
                                            <button class="btn btn-blue btn-sm" onclick='openGerakanFor(<?= $b['id'] ?>,<?= json_encode($b['nama']) ?>, "masuk")' title="Stok Masuk"><i data-lucide="arrow-down-to-line" style="width:13px"></i></button>
                                            <button class="btn btn-orange btn-sm" onclick='openGerakanFor(<?= $b['id'] ?>,<?= json_encode($b['nama']) ?>, "keluar")' title="Stok Keluar"><i data-lucide="arrow-up-from-line" style="width:13px"></i></button>
                                            <button class="btn btn-secondary btn-sm" onclick='openEditBarang(<?= htmlspecialchars(json_encode($b)) ?>)' title="Edit"><i data-lucide="edit-2" style="width:13px"></i></button>
                                            <a href="stok.php?action=del_barang&id=<?= $b['id'] ?>" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Hapus barang ini? Semua riwayat gerakannya juga terhapus!')" title="Hapus">
                                                <i data-lucide="trash-2" style="width:13px"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════ TAB: RIWAYAT GERAKAN ════ -->
        <div class="tpanel <?= $activeTab === 'gerakan' ? 'active' : '' ?>" id="panel-gerakan">
            <div class="card">
                <div class="card-header">
                    <h3><i data-lucide="arrow-left-right" style="width:17px"></i> Riwayat Gerakan Stok (60 terbaru)</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('gerakanModal')">
                        <i data-lucide="plus" style="width:13px"></i> Catat Gerakan
                    </button>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Stok Sebelum</th>
                                <th>Stok Sesudah</th>
                                <th>Keterangan</th>
                                <th>Ref / Transaksi Keuangan</th>
                                <?php if ($canWrite): ?><th>Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($gerakan)): ?>
                                <tr>
                                    <td colspan="<?= $canWrite ? 9 : 8 ?>" style="text-align:center;color:var(--text-gray);padding:2rem">Belum ada riwayat gerakan stok.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($gerakan as $g): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($g['tanggal'])) ?></td>
                                    <td><strong><?= e($g['nama_barang']) ?></strong></td>
                                    <td><?= $g['tipe'] === 'masuk'
                                            ? '<span class="sb ss">↓ Masuk</span>'
                                            : '<span class="sb sd">↑ Keluar</span>' ?></td>
                                    <td><strong style="color:<?= $g['tipe'] === 'masuk' ? '#2563eb' : '#dc2626' ?>">
                                            <?= $g['tipe'] === 'masuk' ? '+' : '-' ?><?= $g['jumlah'] ?> <?= e($g['satuan']) ?>
                                        </strong></td>
                                    <td><?= $g['stok_sebelum'] ?></td>
                                    <td><?= $g['stok_sesudah'] ?></td>
                                    <td><?= e($g['keterangan'] ?? '–') ?></td>
                                    <td>
                                        <?php if ($g['transaksi_id']): ?>
                                            <a href="keuangan.php" class="link-pill" title="Lihat transaksi keuangan #<?= $g['transaksi_id'] ?>">
                                                <i data-lucide="wallet" style="width:11px"></i>
                                                Trx #<?= $g['transaksi_id'] ?>
                                                <?php if ($g['trx_nominal']): ?>
                                                    · <?= formatRupiah((float)$g['trx_nominal']) ?>
                                                <?php endif; ?>
                                            </a>
                                        <?php elseif ($g['referensi']): ?>
                                            <span style="font-size:.8rem;color:var(--text-gray)"><?= e($g['referensi']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-gray);font-size:.8rem">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($canWrite): ?>
                                    <td>
                                        <a href="stok.php?action=del_gerakan&id=<?= $g['id'] ?>" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Hapus catatan ini? Stok akan direcalculate.')" title="Hapus">
                                            <i data-lucide="trash-2" style="width:13px"></i>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════ TAB: STATISTIK ════ -->
        <div class="tpanel <?= $activeTab === 'statistik' ? 'active' : '' ?>" id="panel-statistik">
            <div class="cg">
                <div class="cc">
                    <h3><i data-lucide="bar-chart-2" style="width:18px"></i> Masuk vs Keluar per Barang (Top 10)</h3>
                    <div class="chart-wrap"><canvas id="chartBarang"></canvas></div>
                </div>
                <div class="cc">
                    <h3><i data-lucide="trending-up" style="width:18px"></i> Trend Gerakan 30 Hari</h3>
                    <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i data-lucide="layers" style="width:17px"></i> Ringkasan per Kategori</h3>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jml Item</th>
                                <th>Total Stok</th>
                                <th>Stok Rendah</th>
                                <th>Nilai Stok (HPP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($katStats as $ks): ?>
                                <tr>
                                    <td><?= ucwords(str_replace('_', ' ', $ks['kategori'])) ?></td>
                                    <td><?= $ks['jml'] ?> item</td>
                                    <td><?= number_format($ks['total']) ?> unit</td>
                                    <td><?= $ks['rendah'] > 0 ? '<span class="sb sd">' . $ks['rendah'] . ' item</span>' : '<span class="sb ss">Aman</span>' ?></td>
                                    <td><?= formatRupiah((float)$ks['nilai']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i data-lucide="trending-down" style="width:17px"></i> Top 5 Barang Paling Banyak Keluar</h3>
                </div>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Barang</th>
                                <th>Kategori</th>
                                <th>Total Keluar</th>
                                <th>Stok Sisa</th>
                                <th>Estimasi Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                            foreach ($top5 as $i => $t): ?>
                                <tr>
                                    <td style="font-size:1.1rem"><?= $medals[$i] ?></td>
                                    <td><strong><?= e($t['nama']) ?></strong></td>
                                    <td><?= ucwords(str_replace('_', ' ', $t['kategori'])) ?></td>
                                    <td><strong style="color:#dc2626"><?= number_format($t['tot']) ?> <?= e($t['satuan']) ?></strong></td>
                                    <td><?= $t['stok_saat_ini'] ?> <?= e($t['satuan']) ?></td>
                                    <td><?= $t['harga_jual'] ? formatRupiah($t['tot'] * $t['harga_jual']) : '<span style="color:var(--text-gray)">–</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Bulk Buat Transaksi dari Barang Terpilih ═══ -->
    <div class="modal" id="bulkTrxModal">
        <div class="mbox" style="max-width:500px">
            <div class="mh">
                <h3><i data-lucide="wallet" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Buat Transaksi Keuangan</h3>
                <button class="mcl" onclick="closeModal('bulkTrxModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <p style="font-size:.85rem;color:var(--text-gray);margin-bottom:1.2rem">
                Buat transaksi keuangan untuk <strong id="trxSelectedCount">0</strong> barang terpilih. Nominal dihitung otomatis dari harga × jumlah unit.
            </p>
            <div class="fg">
                <label>Tipe Transaksi *</label>
                <select id="trxTipe" onchange="updateTrxHint()">
                    <option value="pemasukan">↑ Pemasukan (Penjualan — stok keluar)</option>
                    <option value="pengeluaran">↓ Pengeluaran (Pembelian — stok masuk)</option>
                </select>
                <p id="trxHint" style="font-size:.78rem;color:#2563eb;margin-top:.35rem;font-style:italic">
                    Harga jual barang akan digunakan sebagai nominal.
                </p>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem">
                <div class="fg">
                    <label>Jumlah Unit per Barang *</label>
                    <input type="number" id="trxUnit" min="1" value="1" placeholder="1">
                </div>
                <div class="fg">
                    <label>Tanggal *</label>
                    <input type="date" id="trxTgl" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="fg">
                <label>Kategori</label>
                <select id="trxKat">
                    <option value="penjualan">Penjualan</option>
                    <option value="pembelian">Pembelian</option>
                    <option value="operasional">Operasional</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
            <div class="fg">
                <label>Status</label>
                <select id="trxStatus">
                    <option value="pending">Pending</option>
                    <option value="valid">Valid</option>
                </select>
            </div>
            <div style="background:var(--gray);border-radius:10px;padding:.75rem 1rem;font-size:.82rem;color:var(--text-gray);margin-bottom:1rem">
                <i data-lucide="info" style="width:14px;vertical-align:middle"></i>
                Setiap barang terpilih akan dibuat 1 transaksi terpisah di keuangan.php dengan deskripsi otomatis.
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkTrxModal')">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitBulkTrx()">
                    <i data-lucide="send" style="width:14px"></i> Buat Transaksi
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Bulk Tambah Barang ═══ -->
    <div class="modal" id="bulkAddBarangModal">
        <div class="mbox wide">
            <div class="mh">
                <h3><i data-lucide="table-2" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Bulk Tambah Barang
                    <span id="bRowCnt" style="font-size:.78rem;font-weight:400;color:var(--text-gray);margin-left:.5rem">0 baris</span>
                </h3>
                <button class="mcl" onclick="closeModal('bulkAddBarangModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <form method="POST" action="stok.php">
                <input type="hidden" name="action" value="bulk_add_barang">
                <div style="overflow-x:auto;margin-bottom:1rem">
                    <table class="bat">
                        <thead>
                            <tr>
                                <th style="width:30px">#</th>
                                <th>Kode *</th>
                                <th>Nama Barang *</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Stok Awal</th>
                                <th>Stok Min</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th style="width:32px"></th>
                            </tr>
                        </thead>
                        <tbody id="batBodyBarang"></tbody>
                    </table>
                </div>
                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addBarangRow()"><i data-lucide="plus" style="width:13px"></i> Tambah Baris</button>
                    <span style="font-size:.78rem;color:var(--text-gray)">Isi minimal Kode & Nama. Baris kosong akan dilewati.</span>
                    <div style="margin-left:auto;display:flex;gap:.6rem">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkAddBarangModal')">Batal</button>
                        <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Simpan Semua</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ MODAL: Bulk Update Stok Minimum ═══ -->
    <div class="modal" id="bulkUpdateMinModal">
        <div class="mbox" style="max-width:400px">
            <div class="mh">
                <h3><i data-lucide="sliders" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Update Stok Minimum</h3>
                <button class="mcl" onclick="closeModal('bulkUpdateMinModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <p style="font-size:.88rem;color:var(--text-gray);margin-bottom:1.2rem">Set stok minimum untuk semua barang yang dipilih.</p>
            <div class="fg">
                <label>Stok Minimum Baru</label>
                <input type="number" id="inputBulkMin" min="0" value="5" placeholder="Masukkan angka">
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:.5rem">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUpdateMinModal')">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitBulkMin()"><i data-lucide="save" style="width:14px"></i> Update</button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL: Tambah Barang ═══ -->
    <div class="modal" id="addBarangModal">
        <div class="mbox">
            <div class="mh">
                <h3><i data-lucide="package-plus" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Tambah Barang Baru</h3>
                <button class="mcl" onclick="closeModal('addBarangModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <form method="POST" action="stok.php">
                <input type="hidden" name="action" value="add_barang">
                <div class="fr2">
                    <div class="fg"><label>Kode *</label><input type="text" name="kode" required placeholder="BRG-011" style="text-transform:uppercase"></div>
                    <div class="fg"><label>Nama Barang *</label><input type="text" name="nama" required placeholder="Nama barang"></div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Kategori *</label>
                        <select name="kategori" required>
                            <?php foreach (['makanan', 'minuman', 'snack', 'bahan_baku', 'peralatan', 'lainnya'] as $k): ?>
                                <option value="<?= $k ?>"><?= ucwords(str_replace('_', ' ', $k)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg"><label>Satuan *</label>
                        <select name="satuan">
                            <option value="pcs">pcs</option>
                            <option value="porsi">porsi</option>
                            <option value="botol">botol</option>
                            <option value="gelas">gelas</option>
                            <option value="bungkus">bungkus</option>
                            <option value="karung">karung</option>
                            <option value="kg">kg</option>
                            <option value="liter">liter</option>
                            <option value="buah">buah</option>
                        </select>
                    </div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Stok Awal</label><input type="number" name="stok_awal" value="0" min="0"></div>
                    <div class="fg"><label>Stok Minimum (Alert)</label><input type="number" name="stok_minimum" value="5" min="0"></div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Harga Beli (Rp)</label><input type="number" name="harga_beli" min="0" step="500" placeholder="Opsional"></div>
                    <div class="fg"><label>Harga Jual (Rp)</label><input type="number" name="harga_jual" min="0" step="500" placeholder="Opsional"></div>
                </div>
                <div class="fg"><label>Keterangan</label><textarea name="keterangan" placeholder="Deskripsi singkat (opsional)" rows="2"></textarea></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addBarangModal')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ MODAL: Edit Barang ═══ -->
    <div class="modal" id="editBarangModal">
        <div class="mbox">
            <div class="mh">
                <h3><i data-lucide="edit-2" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Edit Barang</h3>
                <button class="mcl" onclick="closeModal('editBarangModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <form method="POST" action="stok.php">
                <input type="hidden" name="action" value="edit_barang">
                <input type="hidden" name="id" id="ebId">
                <div class="fr2">
                    <div class="fg"><label>Kode *</label><input type="text" name="kode" id="ebKode" required style="text-transform:uppercase"></div>
                    <div class="fg"><label>Nama Barang *</label><input type="text" name="nama" id="ebNama" required></div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Kategori</label>
                        <select name="kategori" id="ebKat">
                            <?php foreach (['makanan', 'minuman', 'snack', 'bahan_baku', 'peralatan', 'lainnya'] as $k): ?>
                                <option value="<?= $k ?>"><?= ucwords(str_replace('_', ' ', $k)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg"><label>Satuan</label>
                        <select name="satuan" id="ebSat">
                            <option value="pcs">pcs</option>
                            <option value="porsi">porsi</option>
                            <option value="botol">botol</option>
                            <option value="gelas">gelas</option>
                            <option value="bungkus">bungkus</option>
                            <option value="karung">karung</option>
                            <option value="kg">kg</option>
                            <option value="liter">liter</option>
                            <option value="buah">buah</option>
                        </select>
                    </div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Stok Minimum</label><input type="number" name="stok_minimum" id="ebSmin" min="0"></div>
                    <div class="fg"></div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Harga Beli (Rp)</label><input type="number" name="harga_beli" id="ebHb" min="0" step="500"></div>
                    <div class="fg"><label>Harga Jual (Rp)</label><input type="number" name="harga_jual" id="ebHj" min="0" step="500"></div>
                </div>
                <div class="fg"><label>Keterangan</label><textarea name="keterangan" id="ebKet" rows="2"></textarea></div>
                <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editBarangModal')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ MODAL: Catat Gerakan Stok ═══ -->
    <div class="modal" id="gerakanModal">
        <div class="mbox">
            <div class="mh">
                <h3><i data-lucide="arrow-left-right" style="width:19px;vertical-align:middle;margin-right:.35rem"></i> Catat Gerakan Stok</h3>
                <button class="mcl" onclick="closeModal('gerakanModal')"><i data-lucide="x" style="width:20px"></i></button>
            </div>
            <form method="POST" action="stok.php">
                <input type="hidden" name="action" value="add_gerakan">

                <!-- Tipe: Masuk / Keluar -->
                <div class="tipe-grid">
                    <label class="tipe-opt active-masuk" id="lblMasuk">
                        <input type="radio" name="tipe" value="masuk" checked onchange="tipeCh()">
                        <i data-lucide="arrow-down-to-line" style="width:22px;display:block;margin:0 auto .35rem"></i>
                        Stok Masuk<br><small style="font-weight:400;font-size:.75rem">Barang beli / diterima</small>
                    </label>
                    <label class="tipe-opt" id="lblKeluar">
                        <input type="radio" name="tipe" value="keluar" onchange="tipeCh()">
                        <i data-lucide="arrow-up-from-line" style="width:22px;display:block;margin:0 auto .35rem"></i>
                        Stok Keluar<br><small style="font-weight:400;font-size:.75rem">Barang terjual / dipakai</small>
                    </label>
                </div>

                <div class="fg"><label>Barang *</label>
                    <select name="barang_id" id="gBid" required onchange="barangCh()">
                        <option value="">Pilih Barang</option>
                        <?php foreach ($allBarang as $b): ?>
                            <option value="<?= $b['id'] ?>" data-hb="<?= $b['harga_beli'] ?? 0 ?>" data-hj="<?= $b['harga_jual'] ?? 0 ?>" data-stok="<?= $b['stok_saat_ini'] ?>" data-sat="<?= e($b['satuan']) ?>">
                                <?= e($b['nama']) ?> — Stok: <?= $b['stok_saat_ini'] ?> <?= e($b['satuan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="stokInfo" style="margin-top:.35rem;font-size:.81rem;display:none;padding:.4rem .75rem;background:var(--gray);border-radius:8px;color:var(--text-gray)">
                        Stok saat ini: <strong id="stokNow">0</strong> &nbsp;|&nbsp;
                        Harga beli: <strong id="hBeliInfo">–</strong> &nbsp;|&nbsp;
                        Harga jual: <strong id="hJualInfo">–</strong>
                    </div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Jumlah Unit *</label><input type="number" name="jumlah" id="gJml" required min="1" placeholder="Jumlah" oninput="calcNominalG()"></div>
                    <div class="fg"><label>Tanggal *</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
                </div>
                <div class="fr2">
                    <div class="fg"><label>Keterangan</label><input type="text" name="keterangan" id="gKet" placeholder="Contoh: Beli dari Supplier X"></div>
                    <div class="fg"><label>No. Referensi / Faktur</label><input type="text" name="referensi" placeholder="Opsional"></div>
                </div>

                <!-- Integrasi ke Keuangan -->
                <div class="integ-section">
                    <h4><i data-lucide="link" style="width:15px"></i> Integrasi ke Keuangan (Opsional)</h4>
                    <div class="toggle-row">
                        <input type="checkbox" name="buat_transaksi" id="cbTrx" value="1" onchange="toggleNominal()">
                        <label for="cbTrx" id="lblCbTrx">Buat transaksi keuangan otomatis dari gerakan ini</label>
                    </div>
                    <div id="nominalWrap" style="display:none">
                        <div class="fg" style="margin-bottom:0">
                            <label>Nominal Transaksi (Rp)</label>
                            <input type="number" name="nominal_trx" id="gNominal" min="0" step="500" placeholder="Otomatis dihitung dari harga × jumlah">
                        </div>
                        <p style="font-size:.78rem;color:var(--text-gray);margin-top:.35rem;font-style:italic" id="nominalHint">
                            Stok masuk → transaksi <strong>pengeluaran</strong> (biaya beli)<br>
                            Stok keluar → transaksi <strong>pemasukan</strong> (hasil jual)
                        </p>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('gerakanModal')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms -->
    <form id="formBulkDeleteBarang" method="POST" action="stok.php" style="display:none">
        <input type="hidden" name="action" value="bulk_delete_barang">
        <div id="bdIds"></div>
    </form>

    <!-- Chart data -->
    <script>
        const chartBarangData = <?= json_encode(array_values($chartData)) ?>;
        const chartTrendData = <?= json_encode(array_values($trend)) ?>;
        const dBarang = <?= json_encode(array_column($allBarang, null, 'id')) ?>;
    </script>

    <script>
        lucide.createIcons();

        if (new URLSearchParams(location.search).get('rendah') === '1') {
            const panel = document.getElementById('panel-barang');
            if (panel) setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
        }

        /* Modal */
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            lucide.createIcons();
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
            if (e.target === m) m.classList.remove('active');
        }));

        /* Tabs */
        function showTab(id) {
            document.querySelectorAll('.tpanel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.ntab').forEach(t => t.classList.remove('active'));
            document.getElementById('panel-' + id).classList.add('active');
            const tabs = {
                barang: 0,
                gerakan: 1,
                statistik: 2
            };
            document.querySelectorAll('.ntab')[tabs[id]]?.classList.add('active');
        }

        /* Edit barang */
        function openEditBarang(d) {
            document.getElementById('ebId').value = d.id;
            document.getElementById('ebKode').value = d.kode;
            document.getElementById('ebNama').value = d.nama;
            document.getElementById('ebKat').value = d.kategori;
            document.getElementById('ebSat').value = d.satuan;
            document.getElementById('ebSmin').value = d.stok_minimum;
            document.getElementById('ebHb').value = d.harga_beli || '';
            document.getElementById('ebHj').value = d.harga_jual || '';
            document.getElementById('ebKet').value = d.keterangan || '';
            openModal('editBarangModal');
        }

        /* Gerakan shortcut from row */
        function openGerakanFor(id, nama, tipe) {
            document.getElementById('gBid').value = id;
            document.querySelector(`input[name=tipe][value=${tipe}]`).checked = true;
            tipeCh();
            barangCh();
            openModal('gerakanModal');
        }

        /* Tipe radio style */
        function tipeCh() {
            const masuk = document.querySelector('input[name=tipe][value=masuk]').checked;
            const lM = document.getElementById('lblMasuk');
            const lK = document.getElementById('lblKeluar');
            lM.className = 'tipe-opt' + (masuk ? ' active-masuk' : '');
            lK.className = 'tipe-opt' + (!masuk ? ' active-keluar' : '');
            // Update label integrasi
            const lbl = document.getElementById('lblCbTrx');
            if (lbl) lbl.textContent = masuk ?
                'Buat transaksi pengeluaran otomatis (biaya pembelian)' :
                'Buat transaksi pemasukan otomatis (hasil penjualan)';
            calcNominalG();
            lucide.createIcons();
        }

        /* Barang select → tampilkan info stok + hitung nominal */
        function barangCh() {
            const sel = document.getElementById('gBid');
            const bid = sel.value;
            const info = document.getElementById('stokInfo');
            if (!bid) {
                info.style.display = 'none';
                return;
            }
            const b = dBarang[bid];
            document.getElementById('stokNow').textContent = (b.stok_saat_ini || 0) + ' ' + (b.satuan || '');
            document.getElementById('hBeliInfo').textContent = b.harga_beli ? 'Rp ' + parseInt(b.harga_beli).toLocaleString('id') : '–';
            document.getElementById('hJualInfo').textContent = b.harga_jual ? 'Rp ' + parseInt(b.harga_jual).toLocaleString('id') : '–';
            info.style.display = 'block';
            calcNominalG();
        }

        /* Auto hitung nominal transaksi */
        function calcNominalG() {
            const bid = document.getElementById('gBid').value;
            const jml = parseInt(document.getElementById('gJml').value) || 0;
            const masuk = document.querySelector('input[name=tipe][value=masuk]').checked;
            const nEl = document.getElementById('gNominal');
            if (!bid || !jml || !nEl) return;
            const b = dBarang[bid];
            const harga = masuk ? parseFloat(b.harga_beli || 0) : parseFloat(b.harga_jual || 0);
            if (harga > 0) nEl.value = harga * jml;
        }

        /* Toggle nominal wrap */
        function toggleNominal() {
            const show = document.getElementById('cbTrx').checked;
            document.getElementById('nominalWrap').style.display = show ? 'block' : 'none';
            if (show) calcNominalG();
        }

        /* Charts */
        if (chartBarangData.length) {
            new Chart(document.getElementById('chartBarang'), {
                type: 'bar',
                data: {
                    labels: chartBarangData.map(d => d.nama),
                    datasets: [{
                            label: 'Masuk',
                            data: chartBarangData.map(d => d.masuk),
                            backgroundColor: 'rgba(37,99,235,.8)'
                        },
                        {
                            label: 'Keluar',
                            data: chartBarangData.map(d => d.keluar),
                            backgroundColor: 'rgba(220,38,38,.8)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 30,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        if (chartTrendData.length) {
            new Chart(document.getElementById('chartTrend'), {
                type: 'line',
                data: {
                    labels: chartTrendData.map(d => d.tgl),
                    datasets: [{
                            label: 'Masuk',
                            data: chartTrendData.map(d => d.masuk),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.12)',
                            tension: .35,
                            fill: true
                        },
                        {
                            label: 'Keluar',
                            data: chartTrendData.map(d => d.keluar),
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220,38,38,.12)',
                            tension: .35,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 30,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        /* Toast */
        const toast = document.getElementById('toast');
        if (toast) setTimeout(() => {
            toast.style.animation = 'slideIn .3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, 4000);

        /* Bulk checkbox */
        function getIds() {
            return [...document.querySelectorAll('.rcb:checked')].map(c => +c.value);
        }
        function onCb() {
            const ids = getIds(), tot = document.querySelectorAll('.rcb').length;
            document.getElementById('bbCount').textContent = ids.length + ' dipilih';
            document.getElementById('bbBar').classList.toggle('on', ids.length > 0);
            document.querySelectorAll('.rcb').forEach(c => c.closest('tr').classList.toggle('sel', c.checked));
            const ca = document.getElementById('cbAll');
            if (ca) { ca.checked = ids.length > 0 && ids.length === tot; ca.indeterminate = ids.length > 0 && ids.length < tot; }
        }
        function selAll(v) { document.querySelectorAll('.rcb').forEach(c => c.checked = v); onCb(); }
        function clearSel() { selAll(false); }

        function bulkDeleteBarang() {
            const ids = getIds();
            if (!ids.length) { alert("Pilih barang dulu"); return; }
            if (!confirm("Hapus " + ids.length + " barang? Semua riwayat gerakan terkait juga akan terhapus!")) return;
            document.getElementById("bdIds").innerHTML =
                ids.map(i => `<input type="hidden" name="ids[]" value="${i}">`).join("");
            document.getElementById("formBulkDeleteBarang").submit();
        }

        function openBulkUpdateMin() {
            const ids = getIds();
            if (!ids.length) { alert("Pilih barang dulu"); return; }
            openModal('bulkUpdateMinModal');
        }
        function submitBulkMin() {
            const ids = getIds();
            const val = document.getElementById('inputBulkMin').value;
            if (!ids.length || val === '') return;
            document.getElementById('bulkMinVal').value = val;
            document.getElementById('buIds').innerHTML = ids.map(i => `<input type="hidden" name="ids[]" value="${i}">`).join('');
            document.getElementById('formBulkUpdateMin').submit();
        }

        function openBulkTrx() {
            const ids = getIds();
            if (!ids.length) { alert("Pilih barang dulu"); return; }
            document.getElementById('trxSelectedCount').textContent = ids.length;
            updateTrxHint();
            openModal('bulkTrxModal');
        }
        function updateTrxHint() {
            const tipe = document.getElementById('trxTipe').value;
            const hint = document.getElementById('trxHint');
            const kat  = document.getElementById('trxKat');
            if (tipe === 'pemasukan') {
                hint.style.color = '#2563eb';
                hint.textContent = 'Harga jual barang digunakan. Stok barang akan KELUAR otomatis.';
                kat.value = 'penjualan';
            } else {
                hint.style.color = '#ea580c';
                hint.textContent = 'Harga beli barang digunakan. Stok barang akan MASUK otomatis.';
                kat.value = 'pembelian';
            }
        }
        function submitBulkTrx() {
            const ids  = getIds();
            const tipe = document.getElementById('trxTipe').value;
            const unit = document.getElementById('trxUnit').value;
            const tgl  = document.getElementById('trxTgl').value;
            const kat  = document.getElementById('trxKat').value;
            const st   = document.getElementById('trxStatus').value;
            if (!ids.length || !unit || !tgl) { alert('Lengkapi semua field!'); return; }
            if (!confirm(`Buat ${ids.length} transaksi ${tipe} dan update stok terkait?`)) return;
            document.getElementById('btTipe').value   = tipe;
            document.getElementById('btUnit').value   = unit;
            document.getElementById('btTgl').value    = tgl;
            document.getElementById('btKat').value    = kat;
            document.getElementById('btStatus').value = st;
            document.getElementById('btIds').innerHTML = ids.map(i => `<input type="hidden" name="ids[]" value="${i}">`).join('');
            document.getElementById('formBulkTrx').submit();
        }

        /* Bulk Add Barang */
        let bRowIdx = 0;
        const katOptsB = ['makanan','minuman','snack','bahan_baku','peralatan','lainnya'].map(k =>
            `<option value="${k}">${k.replace('_',' ').replace(/\b\w/g,l=>l.toUpperCase())}</option>`).join('');
        const satOptsB = ['pcs','porsi','botol','gelas','bungkus','karung','kg','liter','buah'].map(s =>
            `<option value="${s}">${s}</option>`).join('');

        function addBarangRow() {
            bRowIdx++;
            const i = bRowIdx;
            const tr = document.createElement('tr'); tr.id = 'bbr' + i;
            tr.innerHTML = `
              <td style="text-align:center;color:var(--text-gray);font-size:.78rem">${i}</td>
              <td><input type="text" name="rows[${i}][kode]" placeholder="BRG-0${String(i).padStart(2,'0')}" style="text-transform:uppercase;min-width:90px"></td>
              <td><input type="text" name="rows[${i}][nama]" placeholder="Nama barang..." style="min-width:130px"></td>
              <td><select name="rows[${i}][kategori]">${katOptsB}</select></td>
              <td><select name="rows[${i}][satuan]">${satOptsB}</select></td>
              <td><input type="number" name="rows[${i}][stok_awal]" value="0" min="0" style="width:70px"></td>
              <td><input type="number" name="rows[${i}][stok_minimum]" value="5" min="0" style="width:60px"></td>
              <td><input type="number" name="rows[${i}][harga_beli]" placeholder="–" min="0" step="500" style="width:100px"></td>
              <td><input type="number" name="rows[${i}][harga_jual]" placeholder="–" min="0" step="500" style="width:100px"></td>
              <td><button type="button" class="dr" onclick="rmBarangRow(${i})" title="Hapus"><i data-lucide="x" style="width:13px"></i></button></td>`;
            document.getElementById('batBodyBarang').appendChild(tr);
            lucide.createIcons();
            updBRowCnt();
        }
        function rmBarangRow(i) { document.getElementById('bbr' + i)?.remove(); updBRowCnt(); }
        function updBRowCnt() { document.getElementById('bRowCnt').textContent = document.getElementById('batBodyBarang').children.length + ' baris'; }
        function openBulkAddBarang() {
            openModal('bulkAddBarangModal');
            if (!document.getElementById('batBodyBarang').children.length) { for (let i = 0; i < 5; i++) addBarangRow(); }
        }
    </script>

<script>
(function(){
  var btn=document.querySelector('.mob-toggle');
  var nav=document.querySelector('.tnav');
  if(btn&&nav){
    btn.addEventListener('click',function(e){
      e.stopPropagation();
      nav.classList.toggle('open');
    });
    document.addEventListener('click',function(e){
      if(!nav.contains(e.target)&&!btn.contains(e.target)){
        nav.classList.remove('open');
      }
    });
  }
})();
</script>
</body>

</html>