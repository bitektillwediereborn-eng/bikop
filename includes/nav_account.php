<a href="profil.php" class="btn btn-secondary btn-sm" title="Profil & Ganti Password">
    <i data-lucide="user-cog" style="width:14px"></i> Profil
</a>
<?php if (isAdmin()): ?>
<a href="akun.php" class="btn btn-secondary btn-sm" title="Kelola Akun">
    <i data-lucide="users" style="width:14px"></i> Akun
</a>
<?php endif; ?>
