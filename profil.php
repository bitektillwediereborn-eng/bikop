<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$action = $_POST['action'] ?? '';

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $userId   = (int)$_SESSION['user_id'];

    if (!$current || !$newPass || !$confirm) {
        setFlash('error', 'Semua field password wajib diisi!');
    } elseif (strlen($newPass) < 6) {
        setFlash('error', 'Password baru minimal 6 karakter.');
    } elseif ($newPass !== $confirm) {
        setFlash('error', 'Konfirmasi password tidak cocok.');
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            setFlash('error', 'Password lama salah.');
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            setFlash('success', 'Password berhasil diubah!');
        }
    }
    header('Location: profil.php');
    exit;
}

$user = $pdo->prepare('SELECT username, nama_lengkap, role, created_at FROM users WHERE id = ?');
$user->execute([(int)$_SESSION['user_id']]);
$profile = $user->fetch();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Akun – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--accent:#2563eb;--dark:#1a1a1a;--gray:#f3f4f6;--gray-dark:#e5e7eb;--text-gray:#6b7280;--shadow:0 4px 6px -1px rgba(0,0,0,.1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#eff6ff,#dbeafe);min-height:100vh;color:var(--dark);}
        .topbar{position:fixed;top:0;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);z-index:1000;box-shadow:var(--shadow);}
        .topbar-inner{max-width:900px;margin:0 auto;padding:.9rem 2rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;}
        .logo{display:flex;align-items:center;gap:.75rem;font-weight:700;color:var(--accent);text-decoration:none;}
        .logo img{width:36px;height:36px;border-radius:8px;}
        .tnav{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
        .main{max-width:900px;margin:0 auto;padding:6rem 2rem 3rem;}
        .card{background:#fff;border-radius:16px;padding:2rem;box-shadow:var(--shadow);margin-bottom:1.5rem;}
        .card h2{font-size:1.4rem;margin-bottom:.25rem;}
        .card p{color:var(--text-gray);font-size:.9rem;margin-bottom:1.5rem;}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:.5rem;}
        .info-item{background:var(--gray);padding:1rem;border-radius:12px;}
        .info-item label{display:block;font-size:.75rem;color:var(--text-gray);margin-bottom:.25rem;font-weight:600;}
        .info-item span{font-weight:600;}
        .role-pill{display:inline-block;background:#dbeafe;color:var(--accent);padding:.2rem .65rem;border-radius:50px;font-size:.8rem;}
        .form-group{margin-bottom:1.1rem;}
        .form-group label{display:block;margin-bottom:.35rem;font-weight:600;font-size:.88rem;}
        .form-group input{width:100%;padding:.8rem 1rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;font-size:.95rem;}
        .form-group input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.15);}
        .btn{padding:.65rem 1.25rem;border-radius:50px;font-weight:600;cursor:pointer;border:none;font-family:inherit;font-size:.85rem;display:inline-flex;align-items:center;gap:.35rem;text-decoration:none;}
        .btn-primary{background:var(--accent);color:#fff;}
        .btn-secondary{background:#dbeafe;color:var(--accent);}
        .btn-danger{background:#fee2e2;color:#dc2626;}
        .toast{position:fixed;top:1rem;right:1rem;z-index:2000;padding:.85rem 1.25rem;border-radius:10px;font-size:.88rem;font-weight:500;box-shadow:var(--shadow);}
        .toast.success{background:#dcfce7;color:#166534;}
        .toast.error{background:#fee2e2;color:#dc2626;}
        @media(max-width:640px){.main{padding:5.5rem 1rem 2rem;}.card{padding:1.25rem;}}

/* Hamburger */
.mob-toggle{display:none;background:none;border:none;cursor:pointer;padding:.45rem;border-radius:8px;color:var(--accent,#2563eb);}
.mob-toggle:hover{background:#eff6ff;}
.mob-toggle svg{width:22px;height:22px;display:block;}
@media(max-width:768px){
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
}
    </style>
</head>
<body>
<?php if ($flash): ?>
<div class="toast <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
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
            <a href="dashboard.php" class="btn btn-secondary"><i data-lucide="layout-dashboard" style="width:14px"></i> Dashboard</a>
            <?php require __DIR__ . '/includes/nav_account.php'; ?>
            <a href="logout.php" class="btn btn-danger" onclick="return confirm('Yakin logout?')"><i data-lucide="log-out" style="width:14px"></i></a>
        </div>
    </div>
</div>

<div class="main">
    <div class="card">
        <h2>Profil Akun</h2>
        <p>Informasi akun Anda saat ini</p>
        <div class="info-grid">
            <div class="info-item"><label>Username</label><span><?= e($profile['username']) ?></span></div>
            <div class="info-item"><label>Nama Lengkap</label><span><?= e($profile['nama_lengkap']) ?></span></div>
            <div class="info-item"><label>Role</label><span class="role-pill"><?= e(roleLabel($profile['role'])) ?></span></div>
            <div class="info-item"><label>Bergabung</label><span><?= date('d M Y', strtotime($profile['created_at'])) ?></span></div>
        </div>
        <?php if (isKasilog()): ?>
        <p style="margin-top:1rem;font-size:.85rem;color:#0d9488;background:#ccfbf1;padding:.75rem 1rem;border-radius:10px;">
            <i data-lucide="eye" style="width:14px;vertical-align:middle"></i>
            Akun Kasilog — akses read-only untuk monitoring pergerakan data.
        </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ganti Password</h2>
        <p>Ubah password akun Anda. Semua role (termasuk Administrator) dapat mengganti password sendiri.</p>
        <form method="POST" action="profil.php">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label for="current_password">Password Lama *</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">Password Baru *</label>
                <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password Baru *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="key" style="width:14px"></i> Simpan Password Baru</button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
<script>
(function(){
  var btn=document.querySelector('.mob-toggle');
  var nav=document.querySelector('.tnav');
  if(btn&&nav){
    btn.addEventListener('click',function(e){e.stopPropagation();nav.classList.toggle('open');});
    document.addEventListener('click',function(e){if(!nav.contains(e.target)&&!btn.contains(e.target)){nav.classList.remove('open');}});
  }
})();
</script>
</body>
</html>
