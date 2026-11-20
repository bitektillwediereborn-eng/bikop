<?php
/**
 * Role & permission helpers
 */

function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isKasilog(): bool
{
    return currentRole() === 'kasilog';
}

function canWrite(): bool
{
    return !isKasilog();
}

function roleLabel(string $role): string
{
    $map = [
        'admin'   => 'Administrator',
        'staff'   => 'Staff',
        'kasilog' => 'Kasilog',
    ];
    return $map[$role] ?? ucfirst($role);
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        if (!function_exists('setFlash')) {
            require_once __DIR__ . '/helpers.php';
        }
        setFlash('error', 'Halaman ini hanya untuk Administrator.');
        header('Location: dashboard.php');
        exit;
    }
}

function blockWriteAction(string $redirectUrl): void
{
    if (canWrite()) {
        return;
    }
    if (!function_exists('setFlash')) {
        require_once __DIR__ . '/helpers.php';
    }
    setFlash('error', 'Akses ditolak. Role Kasilog hanya dapat memonitor (read-only).');
    header('Location: ' . $redirectUrl);
    exit;
}
