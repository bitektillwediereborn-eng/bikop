<?php
// ============================================
// Helper functions untuk flash messages & format
// ============================================

function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function formatRupiah(float $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function statusBadge(string $status): string {
    $map = [
        'baru'      => ['class' => 'status-danger',  'label' => 'Baru'],
        'diproses'  => ['class' => 'status-warning', 'label' => 'Diproses'],
        'selesai'   => ['class' => 'status-success', 'label' => 'Selesai'],
        'dipenuhi'  => ['class' => 'status-success', 'label' => 'Dipenuhi'],
        'ditolak'   => ['class' => 'status-danger',  'label' => 'Ditolak'],
        'pending'   => ['class' => 'status-warning', 'label' => 'Pending'],
        'valid'     => ['class' => 'status-success', 'label' => 'Valid'],
    ];
    $s = $map[$status] ?? ['class' => 'status-warning', 'label' => ucfirst($status)];
    return '<span class="status-badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}
