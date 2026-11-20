<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$canWrite = canWrite();
if ($action !== '') {
    blockWriteAction('saran.php');
}

// UPDATE STATUS
if ($action === 'status' && isset($_GET['id'], $_GET['s'])) {
    $id = (int)$_GET['id'];
    $s  = $_GET['s'];
    if (in_array($s, ['baru','diproses','selesai'])) {
        $pdo->prepare("UPDATE saran SET status=? WHERE id=?")->execute([$s, $id]);
        setFlash('success', 'Status saran diperbarui!');
    }
    header('Location: saran.php');
    exit;
}

// UPDATE CATATAN
if ($action === 'catatan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $catatan = trim($_POST['catatan'] ?? '');
    $status  = $_POST['status'] ?? 'diproses';
    $pdo->prepare("UPDATE saran SET catatan_admin=?, status=? WHERE id=?")->execute([$catatan, $status, $id]);
    setFlash('success', 'Catatan dan status berhasil disimpan!');
    header('Location: saran.php');
    exit;
}

// DELETE
if ($action === 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM saran WHERE id=?")->execute([(int)$_GET['id']]);
    setFlash('success', 'Saran berhasil dihapus!');
    header('Location: saran.php');
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────
$filterStatus   = $_GET['status'] ?? '';
$filterKategori = $_GET['kategori'] ?? '';
$search = trim($_GET['q'] ?? '');
$params = [];
$where  = [];

if ($filterStatus && in_array($filterStatus, ['baru','diproses','selesai'])) {
    $where[] = "status=?"; $params[] = $filterStatus;
}
if ($filterKategori) {
    $where[] = "kategori=?"; $params[] = $filterKategori;
}
if ($search) {
    $where[] = "(nama LIKE ? OR nis_nip LIKE ? OR isi_saran LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';
$stmt = $pdo->prepare("SELECT * FROM saran $whereSQL ORDER BY created_at DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$flash = getFlash();
// View detail
$viewData = null;
if (isset($_GET['view'])) {
    $s = $pdo->prepare("SELECT * FROM saran WHERE id=?");
    $s->execute([(int)$_GET['view']]);
    $viewData = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Saran – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--primary:#93c5fd;--secondary:#bfdbfe;--accent:#2563eb;--dark:#1a1a1a;--gray:#f3f4f6;--gray-dark:#e5e7eb;--text-gray:#6b7280;--shadow:0 4px 6px -1px rgba(0,0,0,.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Poppins',sans-serif;color:var(--dark);background:linear-gradient(135deg,#eff6ff,#dbeafe);min-height:100vh;}
        .topbar{position:fixed;top:0;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);z-index:1000;box-shadow:var(--shadow);}
        .topbar-inner{max-width:1400px;margin:0 auto;padding:.9rem 2rem;display:flex;justify-content:space-between;align-items:center;}
        .logo{display:flex;align-items:center;gap:.75rem;font-size:1.3rem;font-weight:700;color:var(--accent);text-decoration:none;}
        .logo img{width:40px;height:40px;border-radius:8px;}
        .topbar-nav{display:flex;gap:.5rem;align-items:center;}
        .btn{padding:.65rem 1.25rem;border-radius:50px;font-weight:600;cursor:pointer;transition:all .3s;text-decoration:none;border:none;font-size:.85rem;font-family:inherit;display:inline-flex;align-items:center;gap:.35rem;}
        .btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{background:#1d4ed8;}
        .btn-secondary{background:var(--secondary);color:var(--accent);} .btn-secondary:hover{background:var(--primary);}
        .btn-danger{background:#fee2e2;color:#dc2626;} .btn-danger:hover{background:#dc2626;color:#fff;}
        .btn-warning{background:#fef3c7;color:#d97706;} .btn-warning:hover{background:#d97706;color:#fff;}
        .btn-sm{padding:.35rem .75rem;font-size:.78rem;}
        .main{padding:6rem 2rem 3rem;max-width:1400px;margin:0 auto;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
        .page-header h2{font-size:1.8rem;}
        .filter-bar{background:#fff;padding:1rem 1.5rem;border-radius:14px;box-shadow:var(--shadow);margin-bottom:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;}
        .filter-bar input{padding:.6rem 1rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.88rem;flex:1;min-width:180px;}
        .filter-bar input:focus{outline:none;border-color:var(--accent);}
        .filter-btn{padding:.5rem .9rem;border-radius:8px;border:2px solid var(--gray-dark);background:#fff;cursor:pointer;font-family:inherit;font-size:.8rem;font-weight:500;transition:all .3s;text-decoration:none;color:var(--dark);}
        .filter-btn.active,.filter-btn:hover{border-color:var(--accent);color:var(--accent);}
        .card{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
        .card-header{padding:1.25rem 1.5rem;background:var(--secondary);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;}
        .card-header h3{font-size:1.05rem;display:flex;align-items:center;gap:.5rem;}
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:.85rem 1rem;text-align:left;border-bottom:1px solid var(--gray-dark);font-size:.87rem;}
        th{background:#f9fafb;font-weight:600;white-space:nowrap;}
        tr:hover{background:#fafafa;}
        .status-badge{padding:.25rem .75rem;border-radius:50px;font-size:.75rem;font-weight:600;}
        .status-success{background:#dbeafe;color:#2563eb;} .status-warning{background:#fef3c7;color:#d97706;} .status-danger{background:#fee2e2;color:#dc2626;}
        .actions{display:flex;gap:.4rem;}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
        .modal.active{display:flex;}
        .modal-box{background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:540px;animation:popIn .25s;max-height:90vh;overflow-y:auto;}
        @keyframes popIn{from{opacity:0;transform:scale(.9);}to{opacity:1;transform:scale(1);}}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
        .modal-close{background:none;border:none;cursor:pointer;padding:.4rem;border-radius:50%;}
        .modal-close:hover{background:var(--gray);}
        .form-group{margin-bottom:1.1rem;}
        .form-group label{display:block;margin-bottom:.35rem;font-weight:600;font-size:.88rem;}
        .form-group select,.form-group textarea{width:100%;padding:.8rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.92rem;transition:all .3s;}
        .form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.2);}
        .form-group textarea{min-height:100px;resize:vertical;}
        .detail-item{margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--gray-dark);}
        .detail-item:last-child{border:none;margin:0;padding:0;}
        .detail-label{font-size:.8rem;color:var(--text-gray);margin-bottom:.25rem;}
        .detail-value{font-weight:500;}
        .flash-toast{position:fixed;bottom:20px;right:20px;padding:.9rem 1.5rem;border-radius:12px;color:#fff;font-weight:500;z-index:5000;animation:slideIn .3s;max-width:350px;font-size:.9rem;}
        .flash-toast.success{background:var(--accent);} .flash-toast.error{background:#dc2626;}
        @keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
    
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

<div class="topbar">
    <div class="topbar-inner">
        <a href="dashboard.php" class="logo">
            <img src="img/logostinkcl.png" alt="Logo">
            <span>Koperasi Kantin</span>
        </a>
        <button class="mob-toggle" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="tnav">
            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i data-lucide="layout-dashboard" style="width:14px"></i> Dashboard</a>
            <a href="keuangan.php" class="btn btn-secondary btn-sm"><i data-lucide="wallet" style="width:14px"></i> Keuangan</a>
            <a href="kebutuhan.php" class="btn btn-secondary btn-sm"><i data-lucide="shopping-cart" style="width:14px"></i> Kebutuhan</a>
            <?php require __DIR__ . '/includes/nav_account.php'; ?>
            <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin logout?')"><i data-lucide="log-out" style="width:14px"></i></a>
        </div>
    </div>
</div>

<div class="main">
    <?php require __DIR__ . '/includes/readonly_banner.php'; ?>
    <div class="page-header">
        <div>
            <h2>Manajemen Saran & Masukan</h2>
            <p style="color:var(--text-gray)">Kelola saran dari taruna dan pihak eksternal</p>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Cari nama, NIS, atau isi saran..." value="<?= e($search) ?>">
        <a href="saran.php" class="filter-btn <?= !$filterStatus?'active':'' ?>">Semua</a>
        <a href="?status=baru<?= $search?"&q=".urlencode($search):'' ?>" class="filter-btn <?= $filterStatus==='baru'?'active':'' ?>">Baru</a>
        <a href="?status=diproses<?= $search?"&q=".urlencode($search):'' ?>" class="filter-btn <?= $filterStatus==='diproses'?'active':'' ?>">Diproses</a>
        <a href="?status=selesai<?= $search?"&q=".urlencode($search):'' ?>" class="filter-btn <?= $filterStatus==='selesai'?'active':'' ?>">Selesai</a>
        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search" style="width:13px"></i> Cari</button>
    </form>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3><i data-lucide="message-square" style="width:18px"></i> Data Saran (<?= count($rows) ?> data)</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Tanggal</th><th>Nama</th><th>NIS/NIP</th><th>Kategori</th><th>Isi Saran</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if(empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-gray);padding:2rem">Belum ada data saran.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $s): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                    <td><?= e($s['nama']) ?></td>
                    <td><?= e($s['nis_nip']) ?></td>
                    <td><?= ucfirst(e($s['kategori'])) ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($s['isi_saran']) ?>"><?= e($s['isi_saran']) ?></td>
                    <td><?= statusBadge($s['status']) ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="openView(<?= htmlspecialchars(json_encode($s)) ?>)" title="Lihat Detail">
                                <i data-lucide="eye" style="width:13px"></i>
                            </button>
                            <?php if ($canWrite): ?>
                            <?php if ($s['status'] === 'baru'): ?>
                            <a href="saran.php?action=status&id=<?=$s['id']?>&s=diproses" class="btn btn-warning btn-sm" title="Proses">
                                <i data-lucide="clock" style="width:13px"></i>
                            </a>
                            <?php elseif ($s['status'] === 'diproses'): ?>
                            <a href="saran.php?action=status&id=<?=$s['id']?>&s=selesai" class="btn btn-sm" style="background:#dbeafe;color:#2563eb" title="Selesaikan">
                                <i data-lucide="check" style="width:13px"></i>
                            </a>
                            <?php endif; ?>
                            <a href="saran.php?action=delete&id=<?=$s['id']?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Hapus saran ini?')" title="Hapus">
                                <i data-lucide="trash-2" style="width:13px"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail / Catatan -->
<div class="modal" id="viewModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i data-lucide="message-square" style="width:20px;vertical-align:middle;margin-right:.4rem"></i> Detail Saran</h3>
            <button class="modal-close" onclick="closeModal('viewModal')"><i data-lucide="x" style="width:20px"></i></button>
        </div>
        <div id="viewContent"></div>
        <?php if ($canWrite): ?>
        <form method="POST" action="saran.php" style="margin-top:1.5rem;border-top:1px solid var(--gray-dark);padding-top:1.5rem;">
            <input type="hidden" name="action" value="catatan">
            <input type="hidden" name="id" id="viewId">
            <div class="form-group">
                <label>Update Status</label>
                <select name="status" id="viewStatus">
                    <option value="baru">Baru</option>
                    <option value="diproses">Diproses</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
            <div class="form-group">
                <label>Catatan Admin (opsional)</label>
                <textarea name="catatan" id="viewCatatan" placeholder="Tambahkan catatan tanggapan..."></textarea>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:13px"></i> Simpan</button>
            </div>
        </form>
        <?php else: ?>
        <div style="margin-top:1.5rem;border-top:1px solid var(--gray-dark);padding-top:1.5rem;text-align:right;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
lucide.createIcons();
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('active'); }));

function openView(data) {
    document.getElementById('viewId').value = data.id;
    document.getElementById('viewStatus').value = data.status;
    document.getElementById('viewCatatan').value = data.catatan_admin || '';
    document.getElementById('viewContent').innerHTML = `
        <div class="detail-item"><div class="detail-label">Nama</div><div class="detail-value">${data.nama}</div></div>
        <div class="detail-item"><div class="detail-label">NIS/NIP</div><div class="detail-value">${data.nis_nip}</div></div>
        <div class="detail-item"><div class="detail-label">Kategori</div><div class="detail-value">${data.kategori.charAt(0).toUpperCase()+data.kategori.slice(1)}</div></div>
        <div class="detail-item"><div class="detail-label">Tanggal Dikirim</div><div class="detail-value">${data.created_at}</div></div>
        <div class="detail-item"><div class="detail-label">Isi Saran</div><div class="detail-value" style="white-space:pre-wrap;background:var(--gray);padding:1rem;border-radius:8px;margin-top:.4rem">${data.isi_saran}</div></div>
        ${data.catatan_admin ? `<div class="detail-item"><div class="detail-label">Catatan Sebelumnya</div><div class="detail-value" style="color:var(--accent)">${data.catatan_admin}</div></div>` : ''}
    `;
    openModal('viewModal');
    lucide.createIcons();
}

<?php if ($viewData): ?>
openView(<?= json_encode($viewData) ?>);
<?php endif; ?>

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
