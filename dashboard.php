<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$canWrite = canWrite();

// ── Stats ─────────────────────────────────────────────────────────
$totalSaran     = $pdo->query("SELECT COUNT(*) FROM saran")->fetchColumn();
$totalKebutuhan = $pdo->query("SELECT COUNT(*) FROM kebutuhan")->fetchColumn();
$pemasukan      = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tipe='pemasukan' AND status='valid'")->fetchColumn();
$pengeluaran    = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tipe='pengeluaran' AND status='valid'")->fetchColumn();
$saldo          = $pemasukan - $pengeluaran;
$totalVotes     = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$saranBaru      = $pdo->query("SELECT COUNT(*) FROM saran WHERE status='baru'")->fetchColumn();
$kebutuhanBaru  = $pdo->query("SELECT COUNT(*) FROM kebutuhan WHERE status='baru'")->fetchColumn();

// ── Recent data ───────────────────────────────────────────────────
$recentSaran = $pdo->query("SELECT * FROM saran ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentKebutuhan = $pdo->query("SELECT * FROM kebutuhan ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentTransaksi = $pdo->query("SELECT * FROM transaksi ORDER BY tanggal DESC LIMIT 5")->fetchAll();

// ── Menu vote ranking ─────────────────────────────────────────────
$menuVotes = $pdo->query("
    SELECT m.nama_menu, COUNT(v.id) AS total
    FROM menu m LEFT JOIN votes v ON v.menu_id = m.id
    WHERE m.aktif = 1 GROUP BY m.id ORDER BY total DESC
")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--primary:#93c5fd;--primary-dark:#60a5fa;--primary-darker:#3b82f6;--secondary:#bfdbfe;--accent:#2563eb;--dark:#1a1a1a;--gray:#f3f4f6;--gray-dark:#e5e7eb;--text-gray:#6b7280;--shadow:0 4px 6px -1px rgba(0,0,0,.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1);--transition:all .3s ease;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Poppins',sans-serif;color:var(--dark);background:linear-gradient(135deg,#eff6ff,#dbeafe);min-height:100vh;}
        /* Topbar */
        .topbar{position:fixed;top:0;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);z-index:1000;box-shadow:var(--shadow);}
        .topbar-inner{max-width:1400px;margin:0 auto;padding:.9rem 2rem;display:flex;justify-content:space-between;align-items:center;}
        .logo{display:flex;align-items:center;gap:.75rem;font-size:1.3rem;font-weight:700;color:var(--accent);text-decoration:none;}
        .logo img{width:40px;height:40px;border-radius:8px;}
        .user-info{display:flex;align-items:center;gap:1rem;}
        .user-badge{display:flex;align-items:center;gap:.5rem;background:var(--secondary);padding:.5rem 1rem;border-radius:50px;font-size:.85rem;font-weight:500;color:var(--accent);}
        .btn{padding:.65rem 1.25rem;border-radius:50px;font-weight:600;cursor:pointer;transition:var(--transition);text-decoration:none;border:none;font-size:.85rem;font-family:inherit;display:inline-flex;align-items:center;gap:.35rem;}
        .btn-primary{background:var(--accent);color:#fff;}
        .btn-primary:hover{background:#1d4ed8;transform:translateY(-1px);}
        .btn-secondary{background:var(--secondary);color:var(--accent);}
        .btn-secondary:hover{background:var(--primary);}
        .btn-danger{background:#fee2e2;color:#dc2626;}
        .btn-danger:hover{background:#dc2626;color:#fff;}
        .btn-outline{background:transparent;color:var(--accent);border:2px solid var(--accent);}
        .btn-sm{padding:.35rem .75rem;font-size:.8rem;}
        /* Main */
        .main{padding:6rem 2rem 3rem;max-width:1400px;margin:0 auto;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
        .page-header h2{font-size:1.8rem;}
        .page-header p{color:var(--text-gray);font-size:.9rem;}
        /* Stats */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:2.5rem;}
        .stat-card{background:#fff;padding:1.5rem;border-radius:16px;box-shadow:var(--shadow);display:flex;align-items:center;gap:1.25rem;}
        .stat-icon{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;}
        .stat-icon.green{background:#dbeafe;color:var(--accent);}
        .stat-icon.blue{background:#dbeafe;color:#2563eb;}
        .stat-icon.orange{background:#ffedd5;color:#ea580c;}
        .stat-icon.purple{background:#f3e8ff;color:#9333ea;}
        .stat-icon.red{background:#fee2e2;color:#dc2626;}
        .stat-info h4{font-size:.8rem;color:var(--text-gray);margin-bottom:.15rem;}
        .stat-info p{font-size:1.6rem;font-weight:700;}
        .stat-badge{font-size:.7rem;background:#fee2e2;color:#dc2626;padding:.2rem .5rem;border-radius:50px;margin-left:.5rem;}
        /* Nav tabs */
        .nav-tabs{display:flex;gap:.5rem;margin-bottom:2rem;border-bottom:2px solid var(--gray-dark);padding-bottom:.5rem;overflow-x:auto;}
        .nav-tab{padding:.65rem 1.25rem;border:none;background:none;cursor:pointer;font-weight:600;color:var(--text-gray);transition:var(--transition);border-radius:10px 10px 0 0;font-family:inherit;white-space:nowrap;font-size:.9rem;}
        .nav-tab.active{color:var(--accent);background:var(--secondary);}
        .nav-tab:hover{color:var(--accent);}
        .tab-panel{display:none;}
        .tab-panel.active{display:block;}
        /* Card container */
        .card{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:1.5rem;}
        .card-header{padding:1.25rem 1.5rem;background:var(--secondary);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;}
        .card-header h3{font-size:1.05rem;display:flex;align-items:center;gap:.5rem;}
        /* Table */
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:.85rem 1rem;text-align:left;border-bottom:1px solid var(--gray-dark);font-size:.88rem;}
        th{background:#f9fafb;font-weight:600;color:#374151;white-space:nowrap;}
        tr:hover{background:#f9fafb;}
        .status-badge{padding:.25rem .75rem;border-radius:50px;font-size:.75rem;font-weight:600;white-space:nowrap;}
        .status-success{background:#dbeafe;color:#2563eb;}
        .status-warning{background:#fef3c7;color:#d97706;}
        .status-danger{background:#fee2e2;color:#dc2626;}
        /* Modal */
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
        .modal.active{display:flex;}
        .modal-box{background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:560px;animation:popIn .25s ease;max-height:90vh;overflow-y:auto;}
        @keyframes popIn{from{opacity:0;transform:scale(.9);}to{opacity:1;transform:scale(1);}}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
        .modal-header h3{font-size:1.3rem;}
        .modal-close{background:none;border:none;cursor:pointer;padding:.4rem;border-radius:50%;transition:var(--transition);}
        .modal-close:hover{background:var(--gray);}
        .form-group{margin-bottom:1.25rem;}
        .form-group label{display:block;margin-bottom:.4rem;font-weight:600;font-size:.9rem;}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:.85rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.95rem;transition:var(--transition);}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.2);}
        .form-group textarea{min-height:100px;resize:vertical;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        /* Alert */
        .alert{padding:.85rem 1rem;border-radius:10px;margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem;font-size:.9rem;}
        .alert-success{background:#dbeafe;color:var(--accent);border:1px solid #93c5fd;}
        .alert-error{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;}
        /* Flash toast */
        .flash-toast{position:fixed;bottom:20px;right:20px;padding:.9rem 1.5rem;border-radius:12px;color:#fff;font-weight:500;z-index:5000;animation:slideIn .3s ease;max-width:350px;font-size:.9rem;}
        .flash-toast.success{background:var(--accent);}
        .flash-toast.error{background:#dc2626;}
        @keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
        /* Progress bar for votes */
        .vote-bar-wrap{margin-bottom:.75rem;}
        .vote-bar-label{display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem;}
        .vote-bar{height:10px;background:var(--gray-dark);border-radius:50px;overflow:hidden;}
        .vote-bar-fill{height:100%;background:var(--accent);border-radius:50px;transition:width .6s ease;}
        @media(max-width:640px){.form-row{grid-template-columns:1fr;}.stats-grid{grid-template-columns:1fr 1fr;}}
    
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
<div class="flash-toast <?= e($flash['type']) ?>" id="flashToast"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="logo">
            <img src="img/logostinkcl.png" alt="Logo">
            <span>Grey Corps Canteen</span>
        </a>
        <div class="user-info">
            <div class="user-badge">
                <i data-lucide="user" style="width:14px"></i>
                <?= e($_SESSION['nama']) ?> 
                <span style="opacity:.6;font-size:.75rem;">(<?= e(roleLabel($_SESSION['role'])) ?>)</span>
            </div>
            <?php require __DIR__ . '/includes/nav_account.php'; ?>
            <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin logout?')">
                <i data-lucide="log-out" style="width:14px"></i> Logout
            </a>
        </div>
    </div>
</div>

<div class="main">
    <?php require __DIR__ . '/includes/readonly_banner.php'; ?>
    <div class="page-header">
        <div>
            <h2>Dashboard Admin</h2>
            <p>Selamat datang, <?= e($_SESSION['nama']) ?>! – <?= date('d F Y') ?></p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <?php if ($canWrite): ?>
            <a href="keuangan.php" class="btn btn-primary"><i data-lucide="plus" style="width:14px"></i> Tambah Transaksi</a>
            <?php endif; ?>
            <a href="export.php" class="btn btn-secondary"><i data-lucide="download" style="width:14px"></i> Export Laporan</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon green"><i data-lucide="trending-up" style="width:24px"></i></div>
            <div class="stat-info">
                <h4>Total Saldo</h4>
                <p style="font-size:1.2rem;"><?= formatRupiah((float)$saldo) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i data-lucide="message-square" style="width:24px"></i></div>
            <div class="stat-info">
                <h4>Total Saran <span class="stat-badge"><?= $saranBaru ?> baru</span></h4>
                <p><?= $totalSaran ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i data-lucide="shopping-cart" style="width:24px"></i></div>
            <div class="stat-info">
                <h4>Total Kebutuhan <span class="stat-badge"><?= $kebutuhanBaru ?> baru</span></h4>
                <p><?= $totalKebutuhan ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i data-lucide="thumbs-up" style="width:24px"></i></div>
            <div class="stat-info"><h4>Total Votes</h4><p><?= $totalVotes ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i data-lucide="arrow-up-circle" style="width:24px"></i></div>
            <div class="stat-info"><h4>Total Pemasukan</h4><p style="font-size:1.1rem;"><?= formatRupiah((float)$pemasukan) ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i data-lucide="arrow-down-circle" style="width:24px"></i></div>
            <div class="stat-info"><h4>Total Pengeluaran</h4><p style="font-size:1.1rem;"><?= formatRupiah((float)$pengeluaran) ?></p></div>
        </div>
    </div>

    <!-- Nav Tabs -->
    <div class="nav-tabs">
        <button class="nav-tab active" onclick="showPanel('keuangan',this)">
            <i data-lucide="wallet" style="width:14px;vertical-align:middle;margin-right:.3rem"></i> Keuangan
        </button>
        <button class="nav-tab" onclick="showPanel('saran',this)">
            <i data-lucide="message-square" style="width:14px;vertical-align:middle;margin-right:.3rem"></i>
            Saran <?php if($saranBaru>0): ?><span style="background:#dc2626;color:#fff;border-radius:50%;padding:.1rem .4rem;font-size:.7rem;margin-left:.3rem"><?=$saranBaru?></span><?php endif;?>
        </button>
        <button class="nav-tab" onclick="showPanel('kebutuhan',this)">
            <i data-lucide="shopping-cart" style="width:14px;vertical-align:middle;margin-right:.3rem"></i>
            Kebutuhan <?php if($kebutuhanBaru>0): ?><span style="background:#dc2626;color:#fff;border-radius:50%;padding:.1rem .4rem;font-size:.7rem;margin-left:.3rem"><?=$kebutuhanBaru?></span><?php endif;?>
        </button>
        <button class="nav-tab" onclick="showPanel('votes',this)">
            <i data-lucide="bar-chart-2" style="width:14px;vertical-align:middle;margin-right:.3rem"></i> Statistik Vote
        </button>
    </div>

    <!-- Panel: Keuangan -->
    <div class="tab-panel active" id="panel-keuangan">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="wallet" style="width:18px"></i> Transaksi Terbaru</h3>
                <a href="keuangan.php" class="btn btn-primary btn-sm">
                    <i data-lucide="external-link" style="width:14px"></i> Kelola Semua
                </a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tanggal</th><th>Deskripsi</th><th>Tipe</th><th>Jumlah</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTransaksi as $t): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($t['tanggal'])) ?></td>
                        <td><?= e($t['deskripsi']) ?></td>
                        <td><?= $t['tipe'] === 'pemasukan'
                            ? '<span style="color:#2563eb;font-weight:600">↑ Pemasukan</span>'
                            : '<span style="color:#dc2626;font-weight:600">↓ Pengeluaran</span>' ?></td>
                        <td><?= formatRupiah((float)$t['jumlah']) ?></td>
                        <td><?= statusBadge($t['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel: Saran -->
    <div class="tab-panel" id="panel-saran">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="message-square" style="width:18px"></i> Saran Terbaru</h3>
                <a href="saran.php" class="btn btn-primary btn-sm">
                    <i data-lucide="external-link" style="width:14px"></i> Kelola Semua
                </a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tanggal</th><th>Nama</th><th>Tingkat</th><th>Kategori</th><th>Isi Saran</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentSaran as $s): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                        <td><?= e($s['nama']) ?></td>
                        <td><?= e($s['nis_nip']) ?></td>
                        <td><?= ucfirst(e($s['kategori'])) ?></td>
                        <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($s['isi_saran']) ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel: Kebutuhan -->
    <div class="tab-panel" id="panel-kebutuhan">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="shopping-cart" style="width:18px"></i> Kebutuhan Terbaru</h3>
                <a href="kebutuhan.php" class="btn btn-primary btn-sm">
                    <i data-lucide="external-link" style="width:14px"></i> Kelola Semua
                </a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tanggal</th><th>Nama</th><th>Angkatan</th><th>Jenis</th><th>Item</th><th>Jumlah</th><th>Tgl Butuh</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentKebutuhan as $k): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($k['created_at'])) ?></td>
                        <td><?= e($k['nama']) ?></td>
                        <td><?= e($k['angkatan']) ?></td>
                        <td><?= ucfirst(e($k['jenis'])) ?></td>
                        <td><?= e($k['item']) ?></td>
                        <td><?= e($k['jumlah']) ?></td>
                        <td><?= date('d M Y', strtotime($k['tanggal_dibutuhkan'])) ?></td>
                        <td><?= statusBadge($k['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel: Votes -->
    <div class="tab-panel" id="panel-votes">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="bar-chart-2" style="width:18px"></i> Statistik Vote Menu</h3>
            </div>
            <div style="padding:1.5rem;">
                <?php
                $maxVote = max(array_column($menuVotes, 'total') ?: [1]);
                foreach ($menuVotes as $mv):
                    $pct = $maxVote > 0 ? round(($mv['total'] / $maxVote) * 100) : 0;
                ?>
                <div class="vote-bar-wrap">
                    <div class="vote-bar-label">
                        <span><?= e($mv['nama_menu']) ?></span>
                        <strong><?= $mv['total'] ?> votes</strong>
                    </div>
                    <div class="vote-bar">
                        <div class="vote-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();
function showPanel(id, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    if (el) el.classList.add('active');
}
const toast = document.getElementById('flashToast');
if (toast) setTimeout(() => { toast.style.animation = 'slideIn .3s ease reverse'; setTimeout(() => toast.remove(), 300); }, 3500);
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
