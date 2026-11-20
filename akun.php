<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireAdmin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$validRoles = ['admin', 'staff', 'kasilog'];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';

    if (!$username || !$nama || !$password) {
        setFlash('error', 'Username, nama, dan password wajib diisi!');
    } elseif (strlen($password) < 6) {
        setFlash('error', 'Password minimal 6 karakter.');
    } elseif (!in_array($role, $validRoles, true)) {
        setFlash('error', 'Role tidak valid.');
    } else {
        try {
            $pdo->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (?,?,?,?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nama, $role]);
            setFlash('success', "Akun \"$username\" berhasil dibuat.");
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                setFlash('error', 'Username sudah digunakan. Pilih username lain.');
            } elseif (str_contains($e->getMessage(), 'role') || str_contains($e->getMessage(), 'Data truncated')) {
                setFlash('error', 'Role tidak didukung database. Jalankan migration_kasilog_role.sql terlebih dahulu.');
            } else {
                setFlash('error', 'Gagal membuat akun: ' . $e->getMessage());
            }
        }
    }
    header('Location: akun.php');
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = trim($_POST['password'] ?? '');

    if (!$id || !$nama) {
        setFlash('error', 'Data tidak valid.');
    } elseif (!in_array($role, $validRoles, true)) {
        setFlash('error', 'Role tidak valid.');
    } elseif ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
        setFlash('error', 'Anda tidak dapat mengubah role sendiri dari Administrator.');
    } else {
        $target = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
        $target->execute([$id]);
        $targetUser = $target->fetch();

        if (!$targetUser) {
            setFlash('error', 'Akun tidak ditemukan.');
        } elseif ($targetUser['role'] === 'admin' && $role !== 'admin') {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                setFlash('error', 'Tidak dapat mengubah role admin terakhir.');
                header('Location: akun.php');
                exit;
            }
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                setFlash('error', 'Password baru minimal 6 karakter.');
                header('Location: akun.php');
                exit;
            }
            $pdo->prepare('UPDATE users SET nama_lengkap=?, role=?, password=? WHERE id=?')
                ->execute([$nama, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
            setFlash('success', 'Akun dan password berhasil diperbarui.');
        } else {
            $pdo->prepare('UPDATE users SET nama_lengkap=?, role=? WHERE id=?')
                ->execute([$nama, $role, $id]);
            setFlash('success', 'Akun berhasil diperbarui.');
        }
    }
    header('Location: akun.php');
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id === (int)$_SESSION['user_id']) {
        setFlash('error', 'Tidak dapat menghapus akun sendiri.');
    } else {
        $target = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $target->execute([$id]);
        $targetUser = $target->fetch();

        if (!$targetUser) {
            setFlash('error', 'Akun tidak ditemukan.');
        } elseif ($targetUser['role'] === 'admin') {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                setFlash('error', 'Tidak dapat menghapus administrator terakhir.');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                setFlash('success', 'Akun berhasil dihapus.');
            }
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            setFlash('success', 'Akun berhasil dihapus.');
        }
    }
    header('Location: akun.php');
    exit;
}

$users = $pdo->query('SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY role, username')->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun – Koperasi Kantin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--accent:#2563eb;--dark:#1a1a1a;--gray:#f3f4f6;--gray-dark:#e5e7eb;--text-gray:#6b7280;--shadow:0 4px 6px -1px rgba(0,0,0,.1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#eff6ff,#dbeafe);min-height:100vh;color:var(--dark);}
        .topbar{position:fixed;top:0;width:100%;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);z-index:1000;box-shadow:var(--shadow);}
        .topbar-inner{max-width:1100px;margin:0 auto;padding:.9rem 2rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;}
        .logo{display:flex;align-items:center;gap:.75rem;font-weight:700;color:var(--accent);text-decoration:none;}
        .logo img{width:36px;height:36px;border-radius:8px;}
        .tnav{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
        .main{max-width:1100px;margin:0 auto;padding:6rem 2rem 3rem;}
        .ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
        .ph h2{font-size:1.6rem;}
        .card{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
        .card-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-dark);display:flex;justify-content:space-between;align-items:center;}
        .tw{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:.85rem 1rem;text-align:left;border-bottom:1px solid var(--gray);font-size:.88rem;}
        th{background:var(--gray);font-size:.78rem;text-transform:uppercase;color:var(--text-gray);}
        .btn{padding:.55rem 1rem;border-radius:50px;font-weight:600;cursor:pointer;border:none;font-family:inherit;font-size:.82rem;display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;}
        .btn-primary{background:var(--accent);color:#fff;}
        .btn-secondary{background:#dbeafe;color:var(--accent);}
        .btn-danger{background:#fee2e2;color:#dc2626;}
        .btn-sm{padding:.35rem .7rem;font-size:.76rem;}
        .role-pill{display:inline-block;padding:.15rem .55rem;border-radius:50px;font-size:.72rem;font-weight:600;}
        .role-admin{background:#dbeafe;color:#2563eb;}
        .role-staff{background:#f3f4f6;color:#374151;}
        .role-kasilog{background:#ccfbf1;color:#0d9488;}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
        .modal.open{display:flex;}
        .mbox{background:#fff;border-radius:16px;padding:1.5rem;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;}
        .mbox h3{margin-bottom:1rem;}
        .fg{margin-bottom:1rem;}
        .fg label{display:block;margin-bottom:.3rem;font-weight:600;font-size:.85rem;}
        .fg input,.fg select{width:100%;padding:.75rem;border:2px solid var(--gray-dark);border-radius:10px;font-family:inherit;}
        .fg small{color:var(--text-gray);font-size:.75rem;}
        .mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;}
        .toast{position:fixed;top:1rem;right:1rem;z-index:3000;padding:.85rem 1.25rem;border-radius:10px;font-size:.88rem;font-weight:500;box-shadow:var(--shadow);}
        .toast.success{background:#dcfce7;color:#166534;}
        .toast.error{background:#fee2e2;color:#dc2626;}
        .me-badge{font-size:.7rem;background:#fef3c7;color:#b45309;padding:.1rem .45rem;border-radius:50px;margin-left:.35rem;}
        @media(max-width:640px){.main{padding:5.5rem 1rem 2rem;}}

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
        <a href="dashboard.php" class="logo"><img src="img/logostinkcl.png" alt=""><span>Koperasi Kantin</span></a>
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
    <div class="ph">
        <div>
            <h2>Kelola Akun</h2>
            <p style="color:var(--text-gray);font-size:.9rem">Administrator dapat mengelola semua akun internal</p>
        </div>
        <button class="btn btn-primary" onclick="openAdd()"><i data-lucide="user-plus" style="width:14px"></i> Tambah Akun</button>
    </div>

    <div class="card">
        <div class="card-header"><h3><i data-lucide="users" style="width:18px;vertical-align:middle"></i> Daftar Akun (<?= count($users) ?>)</h3></div>
        <div class="tw">
            <table>
                <thead>
                    <tr><th>Username</th><th>Nama</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <?= e($u['username']) ?>
                        <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?><span class="me-badge">Anda</span><?php endif; ?>
                    </td>
                    <td><?= e($u['nama_lengkap']) ?></td>
                    <td><span class="role-pill role-<?= e($u['role']) ?>"><?= e(roleLabel($u['role'])) ?></span></td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick='openEdit(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>
                            <i data-lucide="edit-2" style="width:12px"></i> Edit
                        </button>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <a href="akun.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm"
                           onclick="return confirm('Hapus akun <?= e($u['username']) ?>?')">
                            <i data-lucide="trash-2" style="width:12px"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="addModal">
    <div class="mbox">
        <div class="mh"><h3>Tambah Akun Baru</h3><button type="button" onclick="closeModal('addModal')" style="background:none;border:none;cursor:pointer"><i data-lucide="x"></i></button></div>
        <form method="POST" action="akun.php">
            <input type="hidden" name="action" value="create">
            <div class="fg"><label>Username *</label><input type="text" name="username" required pattern="[a-zA-Z0-9_]+" title="Huruf, angka, underscore"></div>
            <div class="fg"><label>Nama Lengkap *</label><input type="text" name="nama_lengkap" required></div>
            <div class="fg"><label>Role *</label>
                <select name="role" required>
                    <option value="staff">Staff</option>
                    <option value="kasilog">Kasilog (Read-only)</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="fg"><label>Password *</label><input type="password" name="password" required minlength="6"><small>Minimal 6 karakter</small></div>
            <button type="submit" class="btn btn-primary">Simpan Akun</button>
        </form>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="mbox">
        <div class="mh"><h3>Edit Akun</h3><button type="button" onclick="closeModal('editModal')" style="background:none;border:none;cursor:pointer"><i data-lucide="x"></i></button></div>
        <form method="POST" action="akun.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="fg"><label>Username</label><input type="text" id="editUsername" disabled style="background:var(--gray)"></div>
            <div class="fg"><label>Nama Lengkap *</label><input type="text" name="nama_lengkap" id="editNama" required></div>
            <div class="fg"><label>Role *</label>
                <select name="role" id="editRole" required>
                    <option value="staff">Staff</option>
                    <option value="kasilog">Kasilog (Read-only)</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="fg"><label>Password Baru</label><input type="password" name="password" minlength="6"><small>Kosongkan jika tidak ingin mengubah password</small></div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</div>

<script>
lucide.createIcons();
function openAdd(){ document.getElementById('addModal').classList.add('open'); }
function openEdit(u){
    document.getElementById('editId').value = u.id;
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editNama').value = u.nama_lengkap;
    document.getElementById('editRole').value = u.role;
    document.getElementById('editModal').classList.add('open');
}
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
</script>
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
