<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/integrasi.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$canWrite = canWrite();
if ($action !== '') {
    blockWriteAction('keuangan.php');
}

/* ════════════════════════════════════════════════════════
   SINGLE CREATE  (terintegrasi dengan stok)
════════════════════════════════════════════════════════ */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tanggal'     => $_POST['tanggal']     ?? '',
        'deskripsi'   => trim($_POST['deskripsi'] ?? ''),
        'tipe'        => $_POST['tipe']         ?? '',
        'jumlah'      => (float)($_POST['jumlah'] ?? 0),
        'kategori'    => trim($_POST['kategori'] ?? ''),
        'barang_id'   => (int)($_POST['barang_id'] ?? 0) ?: null,
        'jumlah_unit' => (int)($_POST['jumlah_unit'] ?? 0) ?: null,
        'status'      => $_POST['status']       ?? 'pending',
    ];
    if ($data['tanggal'] && $data['tipe'] && $data['jumlah'] > 0) {
        try {
            $res = buatTransaksi($pdo, $data, $_SESSION['user_id']);
            $msg = 'Transaksi berhasil ditambahkan!';
            if ($res['stok_sesudah'] !== null) {
                $msg .= " Stok barang diperbarui → {$res['stok_sesudah']} unit.";
            }
            setFlash('success', $msg);
        } catch (Throwable $e) {
            setFlash('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    } else {
        setFlash('error', 'Semua field wajib diisi dengan benar!');
    }
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   BULK ADD  (terintegrasi dengan stok)
════════════════════════════════════════════════════════ */
if ($action === 'bulk_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = $_POST['rows'] ?? [];
    $ok = 0; $stokUpdated = 0;
    foreach ($rows as $r) {
        $data = [
            'tanggal'     => trim($r['tanggal']   ?? ''),
            'deskripsi'   => trim($r['deskripsi'] ?? ''),
            'tipe'        => trim($r['tipe']       ?? ''),
            'jumlah'      => (float)($r['jumlah']  ?? 0),
            'kategori'    => trim($r['kategori']   ?? ''),
            'barang_id'   => (int)($r['barang_id'] ?? 0) ?: null,
            'jumlah_unit' => (int)($r['jumlah_unit'] ?? 0) ?: null,
            'status'      => $r['status'] ?? 'pending',
        ];
        if ($data['tanggal'] && $data['tipe'] && $data['jumlah'] > 0) {
            try {
                $res = buatTransaksi($pdo, $data, $_SESSION['user_id']);
                $ok++;
                if ($res['stok_sesudah'] !== null) $stokUpdated++;
            } catch (Throwable $e) { /* skip baris error */ }
        }
    }
    $msg = "$ok transaksi berhasil ditambahkan!";
    if ($stokUpdated) $msg .= " $stokUpdated stok barang diperbarui otomatis.";
    setFlash('success', $msg);
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   SINGLE UPDATE
   (update transaksi; stok NOT auto-reversed untuk hindari
    kompleksitas — tampilkan warning ke user)
════════════════════════════════════════════════════════ */
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)$_POST['id'];
    $tanggal   = $_POST['tanggal']   ?? '';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tipe      = $_POST['tipe']      ?? '';
    $jumlah    = (float)($_POST['jumlah'] ?? 0);
    $kategori  = trim($_POST['kategori'] ?? '');
    $status    = $_POST['status']    ?? 'pending';
    if ($id && $tanggal && $deskripsi && $tipe && $jumlah > 0) {
        $pdo->prepare("UPDATE transaksi SET tanggal=?,deskripsi=?,tipe=?,jumlah=?,kategori=?,status=? WHERE id=?")
            ->execute([$tanggal,$deskripsi,$tipe,$jumlah,$kategori?:null,$status,$id]);
        setFlash('success', 'Transaksi diperbarui. Catatan: perubahan tidak mengubah stok secara otomatis.');
    } else { setFlash('error','Data tidak valid!'); }
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   BULK UPDATE STATUS
════════════════════════════════════════════════════════ */
if ($action === 'bulk_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids    = $_POST['ids']    ?? [];
    $status = $_POST['status'] ?? '';
    if ($ids && in_array($status, ['pending','valid','ditolak'])) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE transaksi SET status=? WHERE id IN ($ph)")
            ->execute(array_merge([$status], array_map('intval', $ids)));
        setFlash('success', count($ids).' transaksi diubah ke "'.ucfirst($status).'"!');
    } else { setFlash('error','Pilih data dan status yang valid!'); }
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   SINGLE DELETE  (terintegrasi → rollback stok)
════════════════════════════════════════════════════════ */
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        hapusTransaksi($pdo, (int)$_GET['id']);
        setFlash('success', 'Transaksi dihapus dan stok barang terkait sudah di-rollback!');
    } catch (Throwable $e) {
        setFlash('error', 'Gagal hapus: ' . $e->getMessage());
    }
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   BULK DELETE  (terintegrasi → rollback stok)
════════════════════════════════════════════════════════ */
if ($action === 'bulk_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ok = 0;
    foreach ($ids as $id) {
        try { hapusTransaksi($pdo, $id); $ok++; } catch (Throwable $e) {}
    }
    setFlash('success', "$ok transaksi dihapus dan stok di-rollback!");
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   SINGLE STATUS
════════════════════════════════════════════════════════ */
if ($action === 'status' && isset($_GET['id'], $_GET['s'])) {
    if (in_array($_GET['s'], ['pending','valid','ditolak'])) {
        $pdo->prepare("UPDATE transaksi SET status=? WHERE id=?")->execute([$_GET['s'], (int)$_GET['id']]);
        setFlash('success', 'Status transaksi diperbarui!');
    }
    header('Location: keuangan.php'); exit;
}

/* ════════════════════════════════════════════════════════
   FETCH DATA
════════════════════════════════════════════════════════ */
$filter     = $_GET['filter']  ?? '';
$filterStat = $_GET['fstatus'] ?? '';
$search     = trim($_GET['q']  ?? '');
$dateFrom   = $_GET['from']    ?? '';
$dateTo     = $_GET['to']      ?? '';
$params = []; $where = [];

if ($filter && in_array($filter, ['pemasukan','pengeluaran'])) { $where[]="tipe=?"; $params[]=$filter; }
if ($filterStat && in_array($filterStat,['pending','valid','ditolak'])) { $where[]="status=?"; $params[]=$filterStat; }
if ($search) { $where[]="(t.deskripsi LIKE ? OR t.kategori LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
if ($dateFrom) { $where[]="t.tanggal >= ?"; $params[]=$dateFrom; }
if ($dateTo)   { $where[]="t.tanggal <= ?"; $params[]=$dateTo; }

$wSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';
$stmt = $pdo->prepare("
    SELECT t.*, b.nama AS nama_barang, b.satuan
    FROM transaksi t
    LEFT JOIN barang b ON b.id = t.barang_id
    $wSQL
    ORDER BY t.tanggal DESC, t.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pemasukan   = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tipe='pemasukan' AND status='valid'")->fetchColumn();
$pengeluaran = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tipe='pengeluaran' AND status='valid'")->fetchColumn();
$saldo       = $pemasukan - $pengeluaran;
$pending     = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status='pending'")->fetchColumn();
$withStok    = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE barang_id IS NOT NULL")->fetchColumn();

// Barang untuk dropdown (create/bulk modal)
$allBarang = $pdo->query("SELECT id,nama,satuan,stok_saat_ini,harga_jual,harga_beli FROM barang WHERE aktif=1 ORDER BY nama")->fetchAll();

// Edit data
$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT t.*,b.nama AS nama_barang FROM transaksi t LEFT JOIN barang b ON b.id=t.barang_id WHERE t.id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Keuangan – Koperasi Kantin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root{--primary:#93c5fd;--secondary:#bfdbfe;--accent:#2563eb;--dark:#1a1a1a;--gray:#f3f4f6;--gray-dark:#e5e7eb;--text-gray:#6b7280;--shadow:0 4px 6px -1px rgba(0,0,0,.1);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;color:var(--dark);background:linear-gradient(135deg,#eff6ff,#dbeafe);min-height:100vh;}
.topbar{position:fixed;top:0;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);z-index:1000;box-shadow:var(--shadow);}
.topbar-inner{max-width:1400px;margin:0 auto;padding:.9rem 2rem;display:flex;justify-content:space-between;align-items:center;}
.logo{display:flex;align-items:center;gap:.75rem;font-size:1.3rem;font-weight:700;color:var(--accent);text-decoration:none;}
.logo img{width:40px;height:40px;border-radius:8px;}
.tnav{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
.btn{padding:.65rem 1.25rem;border-radius:50px;font-weight:600;cursor:pointer;transition:all .25s;text-decoration:none;border:none;font-size:.85rem;font-family:inherit;display:inline-flex;align-items:center;gap:.35rem;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{background:#1d4ed8;transform:translateY(-1px);}
.btn-secondary{background:var(--secondary);color:var(--accent);} .btn-secondary:hover{background:var(--primary);}
.btn-danger{background:#fee2e2;color:#dc2626;} .btn-danger:hover{background:#dc2626;color:#fff;}
.btn-info{background:#dbeafe;color:#2563eb;} .btn-info:hover{background:#2563eb;color:#fff;}
.btn-sm{padding:.35rem .75rem;font-size:.78rem;}
.main{padding:6rem 2rem 3rem;max-width:1400px;margin:0 auto;}
.ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.ph h2{font-size:1.8rem;}
/* Integration badge */
.integ-badge{display:inline-flex;align-items:center;gap:.35rem;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border:1.5px solid var(--accent);border-radius:50px;padding:.3rem .85rem;font-size:.78rem;font-weight:600;color:var(--accent);}
/* Stats */
.sg{display:grid;grid-template-columns:repeat(5,1fr);gap:1.1rem;margin-bottom:2rem;}
.sc{background:#fff;padding:1.1rem 1.25rem;border-radius:14px;box-shadow:var(--shadow);display:flex;align-items:center;gap:.9rem;}
.si{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.si.green{background:#dbeafe;color:var(--accent);} .si.blue{background:#dbeafe;color:#2563eb;}
.si.red{background:#fee2e2;color:#dc2626;} .si.orange{background:#ffedd5;color:#ea580c;} .si.purple{background:#f3e8ff;color:#9333ea;}
.sc h4{font-size:.72rem;color:var(--text-gray);margin-bottom:.1rem;} .sc p{font-size:1.1rem;font-weight:700;}
/* Filter */
.fw{background:#fff;padding:1.1rem 1.5rem;border-radius:14px;box-shadow:var(--shadow);margin-bottom:1.5rem;}
.fr{display:flex;gap:.7rem;flex-wrap:wrap;align-items:center;}
.fr input,.fr select{padding:.6rem .9rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;transition:all .25s;}
.fr input:focus,.fr select:focus{outline:none;border-color:var(--accent);}
.fr .si2{flex:1;min-width:180px;}
.chips{display:flex;gap:.4rem;margin-top:.7rem;flex-wrap:wrap;align-items:center;}
.chip{padding:.28rem .8rem;border-radius:50px;font-size:.77rem;font-weight:600;cursor:pointer;text-decoration:none;border:2px solid var(--gray-dark);color:var(--text-gray);background:#fff;transition:all .25s;}
.chip.active,.chip:hover{border-color:var(--accent);color:var(--accent);background:#eff6ff;}
.chip.red.active,.chip.red:hover{border-color:#dc2626;color:#dc2626;background:#fee2e2;}
.csep{width:1px;height:18px;background:var(--gray-dark);}
/* Bulk Bar */
.bb-bar{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;padding:.85rem 1.5rem;border-radius:14px;margin-bottom:1rem;display:none;align-items:center;gap:1rem;flex-wrap:wrap;box-shadow:0 4px 12px rgba(30,64,175,.3);}
.bb-bar.on{display:flex;}
.bba{display:flex;gap:.45rem;flex-wrap:wrap;margin-left:auto;}
.bbb{padding:.4rem .85rem;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-size:.8rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;transition:all .2s;}
.bbb-ok{background:#dbeafe;color:#2563eb;} .bbb-ok:hover{background:#2563eb;color:#fff;}
.bbb-pd{background:#fef3c7;color:#d97706;} .bbb-pd:hover{background:#d97706;color:#fff;}
.bbb-rej{background:#ffedd5;color:#ea580c;} .bbb-rej:hover{background:#ea580c;color:#fff;}
.bbb-del{background:#fee2e2;color:#dc2626;} .bbb-del:hover{background:#dc2626;color:#fff;}
.bbb-cls{background:rgba(255,255,255,.2);color:#fff;} .bbb-cls:hover{background:rgba(255,255,255,.35);}
/* Card + Table */
.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
.card-header{padding:1.1rem 1.5rem;background:var(--secondary);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;}
.card-header h3{font-size:.98rem;display:flex;align-items:center;gap:.5rem;}
.tw{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th,td{padding:.78rem 1rem;text-align:left;border-bottom:1px solid var(--gray-dark);font-size:.84rem;}
th{background:#f9fafb;font-weight:600;color:#374151;white-space:nowrap;}
tr:hover{background:#fafafa;}
tr.sel{background:#eff6ff!important;}
.cbc{width:40px;text-align:center;}
input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:var(--accent);}
.sb{padding:.2rem .65rem;border-radius:50px;font-size:.73rem;font-weight:600;}
.ss{background:#dbeafe;color:#2563eb;} .sw{background:#fef3c7;color:#d97706;} .sd{background:#fee2e2;color:#dc2626;}
.acts{display:flex;gap:.3rem;}
/* Integ pill */
.stok-pill{display:inline-flex;align-items:center;gap:.25rem;background:#eff6ff;border:1px solid var(--accent);border-radius:50px;padding:.12rem .5rem;font-size:.7rem;font-weight:600;color:var(--accent);white-space:nowrap;}
/* Modals */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
.modal.active{display:flex;}
.mbox{background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:600px;animation:popIn .22s;max-height:90vh;overflow-y:auto;}
.mbox.wide{max-width:980px;}
@keyframes popIn{from{opacity:0;transform:scale(.91);}to{opacity:1;transform:scale(1);}}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
.mh h3{font-size:1.15rem;}
.mcl{background:none;border:none;cursor:pointer;padding:.4rem;border-radius:50%;}
.mcl:hover{background:var(--gray);}
.fg{margin-bottom:.95rem;}
.fg label{display:block;margin-bottom:.28rem;font-weight:600;font-size:.84rem;}
.fg input,.fg select,.fg textarea{width:100%;padding:.72rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.88rem;transition:all .25s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.fr2{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;}
/* Integ section in modal */
.integ-section{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid var(--accent);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;}
.integ-section h4{font-size:.88rem;font-weight:700;color:var(--accent);margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;}
.integ-note{font-size:.78rem;color:var(--text-gray);margin-top:.5rem;font-style:italic;}
/* Bulk Add table */
.bat{width:100%;border-collapse:collapse;font-size:.8rem;}
.bat th{background:#eff6ff;padding:.52rem .62rem;font-weight:600;white-space:nowrap;text-align:left;border-bottom:2px solid var(--secondary);}
.bat td{padding:.32rem .38rem;vertical-align:middle;}
.bat input,.bat select{width:100%;padding:.42rem .52rem;border:1.5px solid var(--gray-dark);border-radius:7px;font-family:inherit;font-size:.79rem;background:#fff;}
.bat input:focus,.bat select:focus{outline:none;border-color:var(--accent);}
.form-input-sm{padding:.4rem .65rem;border:1.5px solid var(--gray-dark);border-radius:8px;font-family:inherit;font-size:.8rem;background:#fff;color:var(--dark);height:34px;}
.form-input-sm:focus{outline:none;border-color:var(--accent);}
.bat tr:hover td{background:#fafff8;}
.bat .dr{background:none;border:none;cursor:pointer;color:#dc2626;padding:.22rem;border-radius:5px;}
.bat .dr:hover{background:#fee2e2;}
/* Toast */
.toast{position:fixed;bottom:20px;right:20px;padding:.9rem 1.5rem;border-radius:12px;color:#fff;font-weight:500;z-index:5000;animation:slideIn .3s;max-width:400px;font-size:.88rem;box-shadow:0 4px 14px rgba(0,0,0,.2);}
.toast.success{background:var(--accent);} .toast.error{background:#dc2626;}
@keyframes slideIn{from{transform:translateX(110%);opacity:0;}to{transform:translateX(0);opacity:1;}}
@media(max-width:800px){.sg{grid-template-columns:repeat(2,1fr);}.fr2{grid-template-columns:1fr;}}
@media(max-width:480px){.sg{grid-template-columns:1fr;}}

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

<!-- Topbar -->
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
            <a href="stok.php"      class="btn btn-secondary btn-sm"><i data-lucide="package"          style="width:14px"></i> Stok</a>
            <a href="saran.php"     class="btn btn-secondary btn-sm"><i data-lucide="message-square"   style="width:14px"></i> Saran</a>
            <a href="kebutuhan.php" class="btn btn-secondary btn-sm"><i data-lucide="shopping-cart"    style="width:14px"></i> Kebutuhan</a>
            <?php require __DIR__ . '/includes/nav_account.php'; ?>
            <a href="logout.php"    class="btn btn-danger btn-sm"    onclick="return confirm('Yakin logout?')"><i data-lucide="log-out" style="width:14px"></i></a>
        </div>
    </div>
</div>

<div class="main">
    <?php require __DIR__ . '/includes/readonly_banner.php'; ?>
    <div class="ph">
        <div>
            <h2>Manajemen Keuangan</h2>
            <div style="display:flex;align-items:center;gap:.75rem;margin-top:.3rem;flex-wrap:wrap;">
                <p style="color:var(--text-gray);font-size:.9rem">Pemasukan & Pengeluaran Koperasi</p>
                <span class="integ-badge"><i data-lucide="link" style="width:13px"></i> Terintegrasi dengan Stok</span>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <?php if ($canWrite): ?>
            <button class="btn btn-info"    onclick="openBulkAdd()"><i data-lucide="table-2" style="width:14px"></i> Bulk Tambah</button>
            <button class="btn btn-primary" onclick="openModal('addModal')"><i data-lucide="plus" style="width:14px"></i> Tambah</button>
            <?php endif; ?>
            <a href="export.php?type=keuangan" class="btn btn-secondary"><i data-lucide="download" style="width:14px"></i> Export CSV</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="sg">
        <div class="sc"><div class="si green"><i data-lucide="wallet" style="width:20px"></i></div>
            <div><h4>Saldo</h4><p style="color:var(--accent)"><?= formatRupiah((float)$saldo) ?></p></div></div>
        <div class="sc"><div class="si blue"><i data-lucide="arrow-up-circle" style="width:20px"></i></div>
            <div><h4>Pemasukan</h4><p style="color:#2563eb"><?= formatRupiah((float)$pemasukan) ?></p></div></div>
        <div class="sc"><div class="si red"><i data-lucide="arrow-down-circle" style="width:20px"></i></div>
            <div><h4>Pengeluaran</h4><p style="color:#dc2626"><?= formatRupiah((float)$pengeluaran) ?></p></div></div>
        <div class="sc"><div class="si orange"><i data-lucide="clock" style="width:20px"></i></div>
            <div><h4>Perlu Validasi</h4><p style="color:#ea580c"><?= $pending ?> data</p></div></div>
        <div class="sc"><div class="si purple"><i data-lucide="link" style="width:20px"></i></div>
            <div><h4>Terhubung Stok</h4><p style="color:#9333ea"><?= $withStok ?> transaksi</p></div></div>
    </div>

    <!-- Filter -->
    <div class="fw">
        <form method="GET">
            <div class="fr">
                <input type="text" name="q" class="si2" placeholder="🔍 Cari deskripsi / barang..." value="<?= e($search) ?>">
                <input type="date" name="from" title="Dari tanggal" value="<?= e($dateFrom) ?>">
                <input type="date" name="to"   title="Sampai tanggal" value="<?= e($dateTo) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search" style="width:13px"></i> Filter</button>
                <a href="keuangan.php" class="btn btn-secondary btn-sm">Reset</a>
            </div>
            <div class="chips">
                <span style="font-size:.77rem;font-weight:600;color:var(--text-gray)">Tipe:</span>
                <a href="keuangan.php"                      class="chip <?= !$filter?'active':'' ?>">Semua</a>
                <a href="?filter=pemasukan"                 class="chip <?= $filter==='pemasukan'?'active':'' ?>">↑ Pemasukan</a>
                <a href="?filter=pengeluaran"               class="chip <?= $filter==='pengeluaran'?'active':'' ?>">↓ Pengeluaran</a>
                <div class="csep"></div>
                <span style="font-size:.77rem;font-weight:600;color:var(--text-gray)">Status:</span>
                <a href="keuangan.php<?= $filter?"?filter=$filter":'' ?>"           class="chip <?= !$filterStat?'active':'' ?>">Semua</a>
                <a href="?<?= $filter?"filter=$filter&":''?>fstatus=pending"        class="chip <?= $filterStat==='pending'?'active':'' ?>">Pending</a>
                <a href="?<?= $filter?"filter=$filter&":''?>fstatus=valid"          class="chip <?= $filterStat==='valid'?'active':'' ?>">Valid</a>
                <a href="?<?= $filter?"filter=$filter&":''?>fstatus=ditolak"        class="chip red <?= $filterStat==='ditolak'?'active':'' ?>">Ditolak</a>
            </div>
        </form>
    </div>

    <?php if ($canWrite): ?>
    <!-- Bulk Bar -->
    <div class="bb-bar" id="bbBar">
        <i data-lucide="check-square" style="width:18px;flex-shrink:0"></i>
        <span id="bbCount" style="font-weight:700">0 dipilih</span>
        <div class="bba">
            <button class="bbb bbb-ok"  onclick="bulkSt('valid')"><i data-lucide="check" style="width:12px"></i> Validasi</button>
            <button class="bbb bbb-pd"  onclick="bulkSt('pending')"><i data-lucide="clock" style="width:12px"></i> Pending</button>
            <button class="bbb bbb-rej" onclick="bulkSt('ditolak')"><i data-lucide="x" style="width:12px"></i> Tolak</button>
            <button class="bbb bbb-del" onclick="bulkDel()"><i data-lucide="trash-2" style="width:12px"></i> Hapus + Rollback Stok</button>
            <button class="bbb bbb-cls" onclick="clearSel()">Batal Pilih</button>
        </div>
    </div>

    <!-- Hidden bulk forms -->
    <form id="fBS" method="POST" action="keuangan.php" style="display:none">
        <input type="hidden" name="action" value="bulk_status">
        <input type="hidden" name="status" id="bsv">
        <div id="bsi"></div>
    </form>
    <form id="fBD" method="POST" action="keuangan.php" style="display:none">
        <input type="hidden" name="action" value="bulk_delete">
        <div id="bdi"></div>
    </form>
    <?php endif; ?>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-lucide="wallet" style="width:17px"></i> Data Transaksi
                <span style="background:var(--accent);color:#fff;border-radius:50px;padding:.1rem .55rem;font-size:.72rem;margin-left:.25rem"><?= count($rows) ?></span>
            </h3>
            <?php if ($canWrite): ?>
            <div style="display:flex;gap:.5rem;">
                <button class="btn btn-secondary btn-sm" onclick="selAll(true)"><i data-lucide="check-square" style="width:13px"></i> Pilih Semua</button>
                <button class="btn btn-secondary btn-sm" onclick="selAll(false)">Batal</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="tw">
            <table>
                <thead><tr>
                    <?php if ($canWrite): ?><th class="cbc"><input type="checkbox" id="cbAll" onchange="selAll(this.checked)"></th><?php endif; ?>
                    <th>Tanggal</th><th>Deskripsi</th><th>Tipe</th>
                    <th>Pemasukan</th><th>Pengeluaran</th>
                    <th>Barang Terkait</th><th>Kategori</th><th>Status</th><?php if ($canWrite): ?><th>Aksi</th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php if(empty($rows)): ?>
                <tr><td colspan="<?= $canWrite ? 10 : 8 ?>" style="text-align:center;color:var(--text-gray);padding:2.5rem">Belum ada data transaksi.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $t): ?>
                <tr id="row-<?= $t['id'] ?>">
                    <?php if ($canWrite): ?><td class="cbc"><input type="checkbox" class="rcb" value="<?= $t['id'] ?>" onchange="onCb()"></td><?php endif; ?>
                    <td><?= date('d M Y', strtotime($t['tanggal'])) ?></td>
                    <td><?= e($t['deskripsi']) ?></td>
                    <td><?= $t['tipe']==='pemasukan'
                        ? '<span style="color:#2563eb;font-weight:600">↑ Masuk</span>'
                        : '<span style="color:#dc2626;font-weight:600">↓ Keluar</span>' ?></td>
                    <td><?= $t['tipe']==='pemasukan' ? '<span style="color:#2563eb;font-weight:500">'.formatRupiah((float)$t['jumlah']).'</span>' : '<span style="color:var(--text-gray)">–</span>' ?></td>
                    <td><?= $t['tipe']==='pengeluaran' ? '<span style="color:#dc2626;font-weight:500">'.formatRupiah((float)$t['jumlah']).'</span>' : '<span style="color:var(--text-gray)">–</span>' ?></td>
                    <td>
                        <?php if ($t['nama_barang']): ?>
                        <span class="stok-pill">
                            <i data-lucide="package" style="width:11px"></i>
                            <?= e($t['nama_barang']) ?>
                            <?php if($t['jumlah_unit']): ?>
                            <strong><?= $t['tipe']==='pemasukan'?'–':'+' ?><?= $t['jumlah_unit'] ?> <?= e($t['satuan']??'') ?></strong>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-gray);font-size:.8rem">–</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['kategori'] ?? '–') ?></td>
                    <td><?= statusBadge($t['status']) ?></td>
                    <?php if ($canWrite): ?>
                    <td>
                        <div class="acts">
                            <button class="btn btn-secondary btn-sm" onclick='openEdit(<?= htmlspecialchars(json_encode($t)) ?>)' title="Edit">
                                <i data-lucide="edit-2" style="width:13px"></i>
                            </button>
                            <?php if($t['status']!=='valid'): ?>
                            <a href="keuangan.php?action=status&id=<?=$t['id']?>&s=valid" class="btn btn-sm" style="background:#dbeafe;color:#2563eb" title="Validasi">
                                <i data-lucide="check" style="width:13px"></i>
                            </a>
                            <?php endif; ?>
                            <a href="keuangan.php?action=delete&id=<?=$t['id']?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Hapus transaksi ini?\n<?= $t['nama_barang'] ? 'Stok barang "'.$t['nama_barang'].'" akan di-rollback otomatis!' : '' ?>')" title="Hapus">
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

<!-- ═══ MODAL: Tambah Transaksi ═══ -->
<div class="modal" id="addModal">
    <div class="mbox">
        <div class="mh">
            <h3><i data-lucide="plus-circle" style="width:18px;vertical-align:middle;margin-right:.35rem"></i> Tambah Transaksi</h3>
            <button class="mcl" onclick="closeModal('addModal')"><i data-lucide="x" style="width:20px"></i></button>
        </div>
        <form method="POST" action="keuangan.php">
            <input type="hidden" name="action" value="create">
            <div class="fr2">
                <div class="fg"><label>Tanggal *</label><input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>"></div>
                <div class="fg"><label>Tipe *</label>
                    <select name="tipe" id="addTipe" required onchange="onTipeChange('add')">
                        <option value="">Pilih</option>
                        <option value="pemasukan">↑ Pemasukan (Penjualan)</option>
                        <option value="pengeluaran">↓ Pengeluaran (Pembelian)</option>
                    </select>
                </div>
            </div>
            <div class="fg"><label>Deskripsi *</label><input type="text" name="deskripsi" required placeholder="Contoh: Penjualan Nasi Goreng Siang"></div>
            <div class="fr2">
                <div class="fg"><label>Total Nominal (Rp) *</label><input type="number" name="jumlah" required min="1" step="1" placeholder="150000"></div>
                <div class="fg"><label>Kategori</label>
                    <select name="kategori">
                        <option value="">–</option>
                        <option value="penjualan">Penjualan</option>
                        <option value="pembelian">Pembelian Bahan</option>
                        <option value="operasional">Operasional</option>
                        <option value="gaji">Gaji</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <!-- INTEGRASI STOK -->
            <div class="integ-section">
                <h4><i data-lucide="link" style="width:15px"></i> Integrasi Stok (Opsional)</h4>
                <div class="fr2">
                    <div class="fg"><label>Barang</label>
                        <select name="barang_id" id="addBarang" onchange="autoHarga('add')">
                            <option value="">— Tanpa barang —</option>
                            <?php foreach($allBarang as $b): ?>
                            <option value="<?=$b['id']?>" data-hj="<?=$b['harga_jual']?>" data-hb="<?=$b['harga_beli']?>" data-stok="<?=$b['stok_saat_ini']?>" data-sat="<?=e($b['satuan'])?>">
                                <?= e($b['nama']) ?> (Stok: <?=$b['stok_saat_ini']?> <?=e($b['satuan'])?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg"><label>Jumlah Unit</label>
                        <input type="number" name="jumlah_unit" id="addUnit" min="1" placeholder="Mis: 10 porsi" oninput="calcNominal('add')">
                    </div>
                </div>
                <p class="integ-note" id="addNote">Pilih barang jika transaksi ini terkait dengan penjualan/pembelian stok. Stok akan otomatis berkurang (penjualan) atau bertambah (pembelian).</p>
            </div>

            <div class="fg"><label>Status</label>
                <select name="status"><option value="pending">Pending</option><option value="valid">Valid</option></select>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: Bulk Add ═══ -->
<div class="modal" id="bulkAddModal">
    <div class="mbox wide">
        <div class="mh">
            <h3><i data-lucide="table-2" style="width:18px;vertical-align:middle;margin-right:.35rem"></i> Bulk Tambah Transaksi</h3>
            <button class="mcl" onclick="closeModal('bulkAddModal')"><i data-lucide="x" style="width:20px"></i></button>
        </div>
        <div class="integ-section" style="margin-bottom:.75rem;">
            <h4 style="margin-bottom:0"><i data-lucide="info" style="width:14px"></i> Jika barang dipilih dan jumlah unit diisi → stok otomatis diupdate saat simpan</h4>
        </div>

        <!-- FILL ALL BAR -->
        <div style="background:var(--gray);border:1px solid var(--gray-dark);border-radius:12px;padding:.75rem 1rem;margin-bottom:.75rem;">
            <div style="font-size:.78rem;font-weight:600;color:var(--text-gray);margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem;">
                <i data-lucide="copy-check" style="width:13px"></i> Isi Semua Baris Sekaligus (kosongkan field yang tidak ingin diubah)
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                <input type="date" id="fillAll_tanggal" class="form-input-sm">
                <input type="text" id="fillAll_deskripsi" class="form-input-sm" placeholder="Deskripsi...">
                <select id="fillAll_tipe" class="form-input-sm">
                    <option value="">— Tipe —</option>
                    <option value="pemasukan">↑ Pemasukan</option>
                    <option value="pengeluaran">↓ Pengeluaran</option>
                </select>
                <select id="fillAll_kategori" class="form-input-sm">
                    <option value="">— Kategori —</option>
                    <option value="penjualan">Penjualan</option>
                    <option value="pembelian">Pembelian</option>
                    <option value="operasional">Operasional</option>
                    <option value="gaji">Gaji</option>
                    <option value="lainnya">Lainnya</option>
                </select>
                <select id="fillAll_status" class="form-input-sm">
                    <option value="">— Status —</option>
                    <option value="pending">Pending</option>
                    <option value="valid">Valid</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="applyFillAll()">
                    <i data-lucide="check-check" style="width:13px"></i> Terapkan ke Semua
                </button>
            </div>
        </div>

        <form method="POST" action="keuangan.php" id="baf">
            <input type="hidden" name="action" value="bulk_add">
            <div style="overflow-x:auto;border:1px solid var(--gray-dark);border-radius:12px;">
                <table class="bat">
                    <thead><tr>
                        <th style="width:30px">#</th>
                        <th style="min-width:120px">Tanggal *</th>
                        <th style="min-width:200px">Deskripsi *</th>
                        <th style="min-width:140px">Tipe *</th>
                        <th style="min-width:120px">Nominal (Rp) *</th>
                        <th style="min-width:130px">Kategori</th>
                        <th style="min-width:180px">Barang (Integrasi)</th>
                        <th style="min-width:90px">Jml Unit</th>
                        <th style="min-width:100px">Status</th>
                        <th style="width:30px"></th>
                    </tr></thead>
                    <tbody id="batBody"></tbody>
                </table>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:.9rem;align-items:center;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()"><i data-lucide="plus" style="width:13px"></i> + Baris</button>
                <span style="font-size:.8rem;color:var(--text-gray)" id="rowCnt">5 baris</span>
                <div style="margin-left:auto;display:flex;gap:.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkAddModal')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Simpan Semua</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- ═══ MODAL: Edit ═══ -->
<div class="modal" id="editModal">
    <div class="mbox">
        <div class="mh">
            <h3><i data-lucide="edit-2" style="width:18px;vertical-align:middle;margin-right:.35rem"></i> Edit Transaksi</h3>
            <button class="mcl" onclick="closeModal('editModal')"><i data-lucide="x" style="width:20px"></i></button>
        </div>
        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#92400e;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="alert-triangle" style="width:16px;flex-shrink:0"></i>
            Perubahan di sini tidak mengubah stok. Untuk koreksi stok, gunakan menu Stok → Catat Gerakan.
        </div>
        <form method="POST" action="keuangan.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="eId">
            <div class="fr2">
                <div class="fg"><label>Tanggal *</label><input type="date" name="tanggal" id="eTgl" required></div>
                <div class="fg"><label>Tipe *</label>
                    <select name="tipe" id="eTipe" required>
                        <option value="pemasukan">↑ Pemasukan</option>
                        <option value="pengeluaran">↓ Pengeluaran</option>
                    </select>
                </div>
            </div>
            <div class="fg"><label>Deskripsi *</label><input type="text" name="deskripsi" id="eDesk" required></div>
            <div class="fr2">
                <div class="fg"><label>Nominal (Rp) *</label><input type="number" name="jumlah" id="eJml" required min="1" step="1"></div>
                <div class="fg"><label>Kategori</label>
                    <select name="kategori" id="eKat">
                        <option value="">–</option>
                        <option value="penjualan">Penjualan</option>
                        <option value="pembelian">Pembelian Bahan</option>
                        <option value="operasional">Operasional</option>
                        <option value="gaji">Gaji</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
            </div>
            <div class="fg"><label>Status</label>
                <select name="status" id="eSt">
                    <option value="pending">Pending</option>
                    <option value="valid">Valid</option>
                    <option value="ditolak">Ditolak</option>
                </select>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:14px"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- JSON data barang untuk JS -->
<script>
const dBarang = <?= json_encode(array_column($allBarang, null, 'id')) ?>;
</script>

<script>
lucide.createIcons();

/* Modal */
function openModal(id){ document.getElementById(id).classList.add('active'); lucide.createIcons(); }
function closeModal(id){ document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e=>{ if(e.target===m) m.classList.remove('active'); }));

/* Edit */
function openEdit(d){
    document.getElementById('eId').value   = d.id;
    document.getElementById('eTgl').value  = d.tanggal;
    document.getElementById('eTipe').value = d.tipe;
    document.getElementById('eDesk').value = d.deskripsi;
    document.getElementById('eJml').value  = d.jumlah;
    document.getElementById('eKat').value  = d.kategori||'';
    document.getElementById('eSt').value   = d.status;
    openModal('editModal');
}
<?php if($editData): ?>openEdit(<?= json_encode($editData) ?>);<?php endif; ?>

/* Bulk checkbox */
function getIds(){ return [...document.querySelectorAll('.rcb:checked')].map(c=>+c.value); }
function onCb(){
    const ids=getIds(), tot=document.querySelectorAll('.rcb').length;
    document.getElementById('bbCount').textContent=ids.length+' dipilih';
    document.getElementById('bbBar').classList.toggle('on', ids.length>0);
    document.querySelectorAll('.rcb').forEach(c=>c.closest('tr').classList.toggle('sel',c.checked));
    const ca=document.getElementById('cbAll');
    ca.checked=ids.length>0&&ids.length===tot;
    ca.indeterminate=ids.length>0&&ids.length<tot;
}
function selAll(v){ document.querySelectorAll('.rcb').forEach(c=>c.checked=v); onCb(); }
function clearSel(){ selAll(false); }
function bulkSt(s){
    const ids=getIds(); if(!ids.length){alert('Pilih data dulu!');return;}
    if(!confirm(`Update ${ids.length} transaksi → "${s}"?`)) return;
    document.getElementById('bsv').value=s;
    document.getElementById('bsi').innerHTML=ids.map(i=>`<input type="hidden" name="ids[]" value="${i}">`).join('');
    document.getElementById('fBS').submit();
}
function bulkDel(){
    const ids=getIds(); if(!ids.length){alert('Pilih data dulu!');return;}
    if(!confirm(`Hapus ${ids.length} transaksi?\nStok barang yang terkait akan di-ROLLBACK otomatis!`)) return;
    document.getElementById('bdi').innerHTML=ids.map(i=>`<input type="hidden" name="ids[]" value="${i}">`).join('');
    document.getElementById('fBD').submit();
}

/* Integrasi: auto harga berdasarkan tipe & barang */
function onTipeChange(prefix){
    const tipe   = document.getElementById(prefix+'Tipe')?.value;
    const note   = document.getElementById(prefix+'Note');
    if(!note) return;
    if(tipe==='pemasukan'){
        note.textContent='Penjualan: pilih barang → stok KELUAR otomatis. Harga jual barang akan diisikan.';
    } else if(tipe==='pengeluaran'){
        note.textContent='Pembelian: pilih barang → stok MASUK otomatis. Harga beli barang akan diisikan.';
    } else {
        note.textContent='Pilih barang jika transaksi ini terkait dengan penjualan/pembelian stok.';
    }
    autoHarga(prefix);
}
function autoHarga(prefix){
    const tipe   = document.getElementById(prefix+'Tipe')?.value;
    const sel    = document.getElementById(prefix+'Barang');
    const unitEl = document.getElementById(prefix+'Unit');
    if(!sel||!unitEl) return;
    const bid = sel.value;
    if(!bid||!tipe) return;
    const b   = dBarang[bid];
    if(!b) return;
    // Harga sudah di input nominal → hint saja di placeholder
    const harga = tipe==='pemasukan' ? b.harga_jual : b.harga_beli;
    unitEl.placeholder = `Unit (stok: ${b.stok_saat_ini} ${b.satuan})`;
    calcNominal(prefix);
}
function calcNominal(prefix){
    const tipe   = document.getElementById(prefix+'Tipe')?.value;
    const sel    = document.getElementById(prefix+'Barang');
    const unitEl = document.getElementById(prefix+'Unit');
    const jmlEl  = document.querySelector(`[name=jumlah]`); // single form
    if(!sel||!unitEl||!jmlEl) return;
    const bid  = sel.value;
    const unit = parseInt(unitEl.value)||0;
    if(!bid||!unit||!tipe) return;
    const b     = dBarang[bid];
    const harga = tipe==='pemasukan' ? parseFloat(b.harga_jual||0) : parseFloat(b.harga_beli||0);
    if(harga>0) jmlEl.value = harga * unit;
}

/* Bulk Add rows */
let rIdx=0;
const today = new Date().toISOString().split('T')[0];
const katOpts=`<option value="">–</option><option value="penjualan">Penjualan</option><option value="pembelian">Pembelian</option><option value="operasional">Operasional</option><option value="gaji">Gaji</option><option value="lainnya">Lainnya</option>`;
const stOpts=`<option value="pending">Pending</option><option value="valid">Valid</option>`;
const brOpts=`<option value="">— Tanpa Barang —</option>` + Object.values(dBarang).map(b=>`<option value="${b.id}" data-hj="${b.harga_jual||0}" data-hb="${b.harga_beli||0}">${b.nama} (${b.stok_saat_ini} ${b.satuan})</option>`).join('');

function addRow(){
    rIdx++;
    const i=rIdx;
    const tr=document.createElement('tr'); tr.id='br'+i;
    tr.innerHTML=`
      <td style="text-align:center;color:var(--text-gray);font-size:.78rem">${i}</td>
      <td><input type="date"   name="rows[${i}][tanggal]"     value="${today}"></td>
      <td><input type="text"   name="rows[${i}][deskripsi]"   placeholder="Deskripsi..."></td>
      <td><select              name="rows[${i}][tipe]"        onchange="bulkRowTipe(${i})">
            <option value="">Pilih</option>
            <option value="pemasukan">↑ Pemasukan</option>
            <option value="pengeluaran">↓ Pengeluaran</option>
          </select></td>
      <td><input type="number" name="rows[${i}][jumlah]"      id="rjml${i}" placeholder="0" min="0" step="1"></td>
      <td><select              name="rows[${i}][kategori]"    >${katOpts}</select></td>
      <td><select              name="rows[${i}][barang_id]"   id="rb${i}" onchange="bulkRowHarga(${i})">${brOpts}</select></td>
      <td><input type="number" name="rows[${i}][jumlah_unit]" id="ru${i}" placeholder="–" min="0" oninput="bulkRowCalc(${i})"></td>
      <td><select              name="rows[${i}][status]"      >${stOpts}</select></td>
      <td><button type="button" class="dr" onclick="rmRow(${i})" title="Hapus"><i data-lucide="x" style="width:13px"></i></button></td>`;
    document.getElementById('batBody').appendChild(tr);
    lucide.createIcons();
    updRowCnt();
}
function rmRow(i){ document.getElementById('br'+i)?.remove(); updRowCnt(); }
function updRowCnt(){ document.getElementById('rowCnt').textContent=document.getElementById('batBody').children.length+' baris'; }
function bulkRowTipe(i){ bulkRowHarga(i); }
function bulkRowHarga(i){
    const tipe = document.querySelector(`[name="rows[${i}][tipe]"]`)?.value;
    const bid  = document.getElementById('rb'+i)?.value;
    const uEl  = document.getElementById('ru'+i);
    if(!bid||!tipe||!uEl) return;
    const b = dBarang[bid];
    uEl.placeholder=`Stok: ${b.stok_saat_ini} ${b.satuan}`;
    bulkRowCalc(i);
}
function bulkRowCalc(i){
    const tipe = document.querySelector(`[name="rows[${i}][tipe]"]`)?.value;
    const bid  = document.getElementById('rb'+i)?.value;
    const unit = parseInt(document.getElementById('ru'+i)?.value)||0;
    const jEl  = document.getElementById('rjml'+i);
    if(!bid||!unit||!tipe||!jEl) return;
    const b     = dBarang[bid];
    const harga = tipe==='pemasukan' ? parseFloat(b.harga_jual||0) : parseFloat(b.harga_beli||0);
    if(harga>0) jEl.value = harga * unit;
}

function applyFillAll(){
    const tanggal  = document.getElementById('fillAll_tanggal').value;
    const deskripsi= document.getElementById('fillAll_deskripsi').value;
    const tipe     = document.getElementById('fillAll_tipe').value;
    const kategori = document.getElementById('fillAll_kategori').value;
    const status   = document.getElementById('fillAll_status').value;
    document.querySelectorAll('#batBody tr').forEach(row=>{
        if(tanggal)    { const el=row.querySelector('input[name*="[tanggal]"]');   if(el) el.value=tanggal; }
        if(deskripsi)  { const el=row.querySelector('input[name*="[deskripsi]"]'); if(el) el.value=deskripsi; }
        if(tipe)       { const el=row.querySelector('select[name*="[tipe]"]');     if(el) el.value=tipe; }
        if(kategori)   { const el=row.querySelector('select[name*="[kategori]"]'); if(el) el.value=kategori; }
        if(status)     { const el=row.querySelector('select[name*="[status]"]');   if(el) el.value=status; }
    });
}

function openBulkAdd(){
    openModal('bulkAddModal');
    if(!document.getElementById('batBody').children.length){ for(let i=0;i<5;i++) addRow(); }
}

function openBulkAdd(){
    openModal('bulkAddModal');
    if(!document.getElementById('batBody').children.length){ for(let i=0;i<5;i++) addRow(); }
}

/* Toast */
const toast=document.getElementById('toast');
if(toast) setTimeout(()=>{toast.style.animation='slideIn .3s ease reverse';setTimeout(()=>toast.remove(),300);},4000);
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
