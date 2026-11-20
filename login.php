<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama']      = $user['nama_lengkap'];
            $_SESSION['role']      = $user['role'];
            setFlash('success', 'Login berhasil! Selamat datang, ' . $user['nama_lengkap'] . '.');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    } else {
        $error = 'Username dan password wajib diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Internal – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #93c5fd; --primary-dark: #60a5fa; --primary-darker: #3b82f6;
            --accent: #2563eb; --dark: #1a1a1a; --gray: #f3f4f6; --gray-dark: #e5e7eb;
            --text-gray: #6b7280; --shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-wrapper { width:100%; max-width:420px; padding:1.5rem; }
        .login-card { background:#fff; border-radius:24px; padding:2.5rem; box-shadow:var(--shadow-lg); }
        .login-logo { text-align:center; margin-bottom:1.5rem; }
        .login-logo img { width:80px; height:80px; border-radius:16px; }
        .login-logo h2 { font-size:1.4rem; color:var(--dark); margin-top:.75rem; }
        .login-logo p { color:var(--text-gray); font-size:.9rem; }
        .form-group { margin-bottom:1.25rem; }
        .form-group label { display:block; margin-bottom:.4rem; font-weight:600; font-size:.9rem; color:var(--dark); }
        .input-wrap { position:relative; }
        .input-wrap i { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); color:var(--text-gray); width:18px; }
        .form-group input, .form-group select {
            width:100%; padding:.85rem .85rem .85rem 2.5rem;
            border:2px solid var(--gray-dark); border-radius:12px;
            font-family:inherit; font-size:1rem; transition:all .3s;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(37,99,235,.2); }
        .btn { width:100%; padding:.9rem; border-radius:50px; font-weight:600; cursor:pointer; border:none; font-size:1rem; font-family:inherit; background:var(--accent); color:#fff; transition:all .3s; }
        .btn:hover { background:#1d4ed8; transform:translateY(-1px); box-shadow:var(--shadow-lg); }
        .alert { padding:.9rem 1rem; border-radius:10px; margin-bottom:1.25rem; font-size:.9rem; display:flex; align-items:center; gap:.5rem; }
        .alert-error { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; }
        .back-link { text-align:center; margin-top:1.25rem; }
        .back-link a { color:var(--accent); text-decoration:none; font-size:.9rem; font-weight:500; }
        .role-note { background:var(--gray); padding:.75rem 1rem; border-radius:10px; margin-bottom:1.5rem; font-size:.8rem; color:var(--text-gray); }
        @media(max-width:480px){
            .login-card{padding:1.75rem 1.25rem;border-radius:18px;}
            .login-wrapper{padding:1rem;}
            .login-logo img{width:64px;height:64px;}
            .login-logo h2{font-size:1.2rem;}
        }
    
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
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <img src="img/logostinkcl.png" alt="Logo">
            <h2>Login Internal</h2>
            <p>Dashboard Grey Corps Canteen</p>
            <p>RESIMEN KORPS</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i data-lucide="alert-circle" style="width:16px"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <!-- <div class="role-note">
            <strong>Demo:</strong> username <code>admin</code> / password <code>password</code>
        </div> -->

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <i data-lucide="user"></i>
                    <input type="text" id="username" name="username" required placeholder="Masukkan username"
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i data-lucide="lock"></i>
                    <input type="password" id="password" name="password" required placeholder="Masukkan password">
                </div>
            </div>
            <button type="submit" class="btn">
                Masuk ke Dashboard
            </button>
        </form>

        <div class="back-link">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
