<?php
header('Location: login.php');
exit;

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$flash    = getFlash();
$tab      = 'saran';
$formError = '';

// ── Handle POST: Saran ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'saran') {
    $nama     = trim($_POST['nama'] ?? '');
    $nis_nip  = trim($_POST['nis_nip'] ?? '');
    $kategori = $_POST['kategori'] ?? '';
    $isi      = trim($_POST['saran'] ?? '');

    if ($nama && $nis_nip && $kategori && $isi) {
        $stmt = $pdo->prepare("INSERT INTO saran (nama, nis_nip, kategori, isi_saran) VALUES (?,?,?,?)");
        $stmt->execute([$nama, $nis_nip, $kategori, $isi]);
        setFlash('success', 'Saran berhasil dikirim! Terima kasih atas masukan Anda.');
        header('Location: index.php#public');
        exit;
    } else {
        $formError = 'Semua field wajib diisi!';
        $tab = 'saran';
    }
}

// ── Handle POST: Kebutuhan ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kebutuhan') {
    $nama      = trim($_POST['namaK'] ?? '');
    $angkatan  = trim($_POST['angkatan'] ?? '');
    $jenis     = $_POST['jenisK'] ?? '';
    $item      = trim($_POST['itemK'] ?? '');
    $jumlah    = trim($_POST['jumlah'] ?? '');
    $tanggal   = $_POST['tanggal'] ?? '';

    if ($nama && $angkatan && $jenis && $item && $jumlah && $tanggal) {
        $stmt = $pdo->prepare("INSERT INTO kebutuhan (nama, angkatan, jenis, item, jumlah, tanggal_dibutuhkan) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$nama, $angkatan, $jenis, $item, $jumlah, $tanggal]);
        setFlash('success', 'Kebutuhan berhasil diajukan!');
        header('Location: index.php#public');
        exit;
    } else {
        $formError = 'Semua field wajib diisi!';
        $tab = 'kebutuhan';
    }
}

// ── Handle POST: Vote ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vote') {
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    $voterKey = md5(($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') . date('Y-m-d'));

    if ($menu_id) {
        // Cek sudah vote hari ini
        $check = $pdo->prepare("SELECT id FROM votes WHERE voter_session = ? AND DATE(created_at) = CURDATE()");
        $check->execute([$voterKey]);
        if ($check->fetch()) {
            setFlash('error', 'Anda sudah memberikan vote hari ini!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO votes (menu_id, voter_ip, voter_session) VALUES (?,?,?)");
            $stmt->execute([$menu_id, $_SERVER['REMOTE_ADDR'] ?? null, $voterKey]);
            setFlash('success', 'Vote berhasil dikirim!');
        }
    }
    header('Location: index.php#public');
    exit;
}

// ── Ambil data menu + vote count ─────────────────────────────────
$menus = $pdo->query("
    SELECT m.id, m.nama_menu, m.deskripsi, m.harga,
           COUNT(v.id) AS total_votes
    FROM menu m
    LEFT JOIN votes v ON v.menu_id = m.id
    WHERE m.aktif = 1
    GROUP BY m.id
    ORDER BY total_votes DESC
")->fetchAll();

// Cek apakah user sudah vote hari ini
$voterKey = md5(($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') . date('Y-m-d'));
$hasVoted = $pdo->prepare("SELECT id FROM votes WHERE voter_session = ? AND DATE(created_at) = CURDATE()");
$hasVoted->execute([$voterKey]);
$sudahVote = (bool)$hasVoted->fetch();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koperasi Kantin – Wirausaha Bersama</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #93c5fd;
            --primary-dark: #60a5fa;
            --primary-darker: #3b82f6;
            --secondary: #bfdbfe;
            --accent: #2563eb;
            --dark: #1a1a1a;
            --light: #fff;
            --gray: #f3f4f6;
            --gray-dark: #e5e7eb;
            --text-gray: #6b7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .1);
            --transition: all .3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, .95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
        }

        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:hover {
            color: var(--accent);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: var(--transition);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: .75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            font-size: .9rem;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-darker);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            color: var(--accent);
            border: 2px solid var(--accent);
        }

        .btn-outline:hover {
            background: var(--accent);
            color: #fff;
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--accent);
        }

        .btn-secondary:hover {
            background: var(--primary);
        }

        /* Hero */
        .hero {
            padding: 10rem 2rem 5rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }

        .hero-content h1 span {
            color: var(--accent);
        }

        .hero-content p {
            font-size: 1.1rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-image {
            position: relative;
        }

        .hero-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .floating-card {
            position: absolute;
            background: #fff;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: .75rem;
            animation: float 3s ease-in-out infinite;
        }

        .floating-card.card-1 {
            top: 10%;
            left: -10%;
        }

        .floating-card.card-2 {
            bottom: 20%;
            right: -5%;
            animation-delay: 1.5s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Features */
        .features {
            padding: 5rem 2rem;
            background: #fff;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            text-align: center;
            color: var(--text-gray);
            margin-bottom: 3rem;
        }

        .features-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 2rem;
            border-radius: 20px;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: var(--text-gray);
        }

        /* Access */
        .access-section {
            padding: 5rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 3rem;
        }

        .access-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .access-card:hover {
            transform: translateY(-5px);
        }

        .access-header {
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
        }

        .access-header h3 {
            font-size: 1.75rem;
            margin-bottom: .5rem;
        }

        .access-header p {
            opacity: .9;
        }

        .access-body {
            padding: 2rem;
        }

        .access-body ul {
            list-style: none;
            margin-bottom: 2rem;
        }

        .access-body li {
            padding: .75rem 0;
            display: flex;
            align-items: center;
            gap: .75rem;
            color: var(--text-gray);
        }

        /* Forms Section */
        .form-section {
            padding: 5rem 2rem;
            background: #fff;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: .5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-dark);
            border-radius: 10px;
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .2);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: .5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-dark);
            padding-bottom: .5rem;
        }

        .tab {
            padding: .75rem 1.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-gray);
            transition: var(--transition);
            border-radius: 10px 10px 0 0;
            font-family: inherit;
        }

        .tab.active {
            color: var(--accent);
            background: var(--secondary);
        }

        .tab:hover {
            color: var(--accent);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Vote Cards */
        .vote-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .vote-card {
            background: #fff;
            border: 2px solid var(--gray-dark);
            border-radius: 15px;
            padding: 1.25rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .vote-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
        }

        .vote-card.selected {
            border-color: var(--accent);
            background: var(--secondary);
        }

        .vote-card h4 {
            font-size: 1rem;
            margin-bottom: .25rem;
        }

        .vote-card p {
            font-size: .8rem;
            color: var(--text-gray);
        }

        .vote-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            display: block;
            margin-top: .5rem;
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: #fff;
            padding: 3rem 2rem;
            margin-top: 5rem;
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: .5rem;
        }

        .footer-links a {
            color: #d1d5db;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            max-width: 1400px;
            margin: 2rem auto 0;
            padding-top: 2rem;
            border-top: 1px solid #374151;
            text-align: center;
            color: #9ca3af;
        }

        /* Alert */
        .alert {
            padding: .9rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .95rem;
        }

        .alert-success {
            background: #dbeafe;
            color: var(--accent);
            border: 1px solid #93c5fd;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        /* Flash notification fixed */
        .flash-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            color: #fff;
            font-weight: 500;
            z-index: 5000;
            animation: slideIn .3s ease;
            max-width: 350px;
        }

        .flash-toast.success {
            background: var(--accent);
        }

        .flash-toast.error {
            background: #dc2626;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media(max-width:768px) {
            .nav-links {
                display: none;
            }

            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .hero-buttons {
                justify-content: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .access-grid {
                grid-template-columns: 1fr;
            }

            .floating-card {
                display: none;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeIn .6s ease forwards;
        }

        /* ═══════════════════════════════════════
   RESPONSIVE – MOBILE FIRST
   Breakpoints: 480 | 640 | 768 | 1024
═══════════════════════════════════════ */

        /* Hamburger button – only visible on mobile */
        .mob-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: .45rem;
            border-radius: 8px;
            color: var(--accent, #2563eb);
        }

        .mob-toggle:hover {
            background: #eff6ff;
        }

        .mob-toggle svg {
            width: 22px;
            height: 22px;
            display: block;
        }

        /* Mobile nav drawer */
        .tnav {
            transition: all .25s;
        }

        @media(max-width:768px) {

            /* ── Topbar ── */
            .topbar-inner {
                padding: .75rem 1rem;
            }

            .logo span {
                font-size: 1.1rem;
            }

            .mob-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .tnav {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: #fff;
                flex-direction: column;
                padding: 1rem;
                box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
                z-index: 999;
                gap: .5rem;
                border-top: 2px solid #dbeafe;
            }

            .tnav.open {
                display: flex;
            }

            .tnav .btn {
                width: 100%;
                justify-content: flex-start;
                border-radius: 10px;
            }

            /* ── Main padding ── */
            .main {
                padding: 5rem 1rem 2rem !important;
            }

            /* ── Page header ── */
            .ph {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .ph>div:last-child {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: .5rem;
            }

            .ph .btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }

            /* ── Stats grids ── */
            .sg,
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .sc {
                padding: .85rem 1rem;
            }

            /* ── Filter bar ── */
            .fw {
                padding: .85rem 1rem;
            }

            .fr {
                flex-direction: column;
                gap: .5rem;
            }

            .fr input,
            .fr select {
                width: 100%;
            }

            .fr .si2 {
                min-width: unset;
                width: 100%;
            }

            .chips {
                gap: .3rem;
            }

            /* ── Tables: horizontal scroll ── */
            .tw {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .tw table {
                min-width: 600px;
            }

            table th,
            table td {
                padding: .6rem .75rem;
                font-size: .78rem;
            }

            /* ── Card header ── */
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: .5rem;
            }

            .card-header .btn {
                align-self: flex-start;
            }

            /* ── Bulk bar ── */
            .bb-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .bba {
                width: 100%;
                flex-wrap: wrap;
            }

            /* ── Modals: full screen on mobile ── */
            .modal {
                align-items: flex-end;
                padding: 0;
            }

            .mbox,
            .mbox.wide {
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 20px 20px 0 0;
                max-height: 92vh;
                padding: 1.5rem 1rem;
            }

            .fr2 {
                grid-template-columns: 1fr !important;
            }

            /* ── Chart grid ── */
            .cg {
                grid-template-columns: 1fr !important;
            }

            /* ── Tab nav ── */
            .ntabs {
                gap: .25rem;
            }

            .ntab {
                padding: .55rem .85rem;
                font-size: .8rem;
            }

            /* ── Integ section ── */
            .integ-section {
                padding: .75rem 1rem;
            }

            /* ── Action buttons in table ── */
            .acts {
                gap: .2rem;
                flex-wrap: wrap;
            }

            /* ── Tipe radio grid ── */
            .tipe-grid {
                grid-template-columns: 1fr 1fr;
            }

            /* ── Bulk add table wrapper ── */
            #bulkAddModal .mbox {
                padding: 1rem .75rem;
            }
        }

        @media(max-width:480px) {

            /* ── Single-col stats on tiny screens ── */
            .sg,
            .stats-grid {
                grid-template-columns: 1fr !important;
            }

            /* ── Logo: hide text ── */
            .logo span {
                display: none;
            }

            /* ── Chips wrap tightly ── */
            .chip {
                font-size: .7rem;
                padding: .22rem .6rem;
            }

            /* ── Page title smaller ── */
            .ph h2 {
                font-size: 1.4rem;
            }

            /* ── Button text shorter ── */
            .btn {
                font-size: .8rem;
                padding: .55rem 1rem;
            }

            /* ── Table even more compact ── */
            table th,
            table td {
                padding: .5rem .6rem;
                font-size: .74rem;
            }
        }

        @media(min-width:769px) and (max-width:1024px) {

            /* ── Medium screens: 3-col stats ── */
            .sg,
            .stats-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .main {
                padding: 6rem 1.5rem 2.5rem !important;
            }
        }

        /* Responsive One Stop Services */
        @media (max-width: 768px) {
            .form-section {
                padding: 1rem;
            }

            .form-container {
                padding: 1rem;
            }

            .tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .tab {
                flex: 1 1 auto;
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
                text-align: center;
            }

            .form-row {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
                box-sizing: border-box;
                font-size: 1rem;
                /* Cegah auto-zoom di iOS */
            }

            .vote-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }

            .vote-card h4 {
                font-size: 0.85rem;
            }

            .btn {
                font-size: 0.95rem;
                padding: 0.75rem;
            }
        }

        @media (max-width: 400px) {
            .vote-grid {
                grid-template-columns: 1fr;
            }

            .tab {
                font-size: 0.78rem;
                padding: 0.4rem 0.5rem;
            }
        }
    </style>
</head>

<body>

    <!-- Flash Toast -->
    <?php if ($flash): ?>
        <div class="flash-toast <?= e($flash['type']) ?>" id="flashToast">
            <?= e($flash['msg']) ?>
        </div>
    <?php endif; ?>
    <?php if ($_GET['logout'] ?? false): ?>
        <div class="flash-toast success" id="flashToast">Logout berhasil. Sampai jumpa!</div>
    <?php endif; ?>

    <!-- Header -->
    <header>
        <div class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logostinkcl.png" alt="Logo">
                <span>Grey Corps Canteen</span>
            </a>
            <ul class="nav-links">
                <!-- <li><a href="#home">Beranda</a></li>
                <li><a href="#public">Publik</a></li> -->
            </ul>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-outline">
                    <i data-lucide="lock" style="width:16px"></i> Login Internal
                </a>
                <!-- <a href="#public" class="btn btn-primary">Akses Publik</a> -->
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content animate-in">
                <h1>Grey Corps Canteen</h1>
                <h1><span>Wirausaha</span> Bersama</h1>
                <p>Platform terintegrasi untuk pengelolaan koperasi kantin dengan akses internal untuk pelaporan keuangan dan akses publik untuk saran serta pengelolaan kebutuhan taruna.</p>
                <!-- <div class="hero-buttons">
                    <a href="#public" class="btn btn-primary">
                        <i data-lucide="message-circle" style="width:16px"></i> Beri Saran
                    </a>
                    <a href="login.php" class="btn btn-outline">f
                        <i data-lucide="lock" style="width:16px"></i> Akses Internal
                    </a>
                </div> -->
            </div>
            <div class="hero-image animate-in">
                <img src="https://image.qwenlm.ai/public_source/1dde1ac8-164a-402f-bced-038e4013f839/187b2309f-17a4-4685-bc0d-89bb95233c95.png" alt="Kantin Koperasi">
                <div class="floating-card card-1">
                    <i data-lucide="users" style="color:var(--accent);width:24px"></i>
                    <div><strong>900+</strong>
                        <p style="font-size:.8rem;color:var(--text-gray);">Taruna Aktif</p>
                    </div>
                </div>
                <div class="floating-card card-2">
                    <i data-lucide="trending-up" style="color:var(--accent);width:24px"></i>
                    <div><strong>+25%</strong>
                        <p style="font-size:.8rem;color:var(--text-gray);">Pertumbuhan</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <!-- <section class="features" id="features">
    <h2 class="section-title">Layanan Kami</h2>
    <p class="section-subtitle">Solusi lengkap untuk pengelolaan koperasi kantin modern</p>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="file-text" style="width:28px"></i></div>
            <h3>Pelaporan Keuangan</h3>
            <p>Sistem pelaporan keuangan bulanan yang transparan dan akurat untuk internal koperasi.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="message-square" style="width:28px"></i></div>
            <h3>Saran & Masukan</h3>
            <p>Saluran komunikasi terbuka untuk taruna memberikan saran dan masukan perbaikan.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="shopping-cart" style="width:28px"></i></div>
            <h3>Kelola Kebutuhan</h3>
            <p>Pengelolaan kebutuhan makanan dan minuman berdasarkan permintaan taruna.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="shield-check" style="width:28px"></i></div>
            <h3>Akses Aman</h3>
            <p>Sistem keamanan berlapis untuk melindungi data internal koperasi.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="bar-chart-2" style="width:28px"></i></div>
            <h3>Analitik Data</h3>
            <p>Dashboard analitik untuk memantau kinerja dan tren penjualan.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i data-lucide="leaf" style="width:28px"></i></div>
            <h3>Wirausaha Hijau</h3>
            <p>Konsep bisnis berkelanjutan dengan prinsip ramah lingkungan.</p>
        </div>
    </div>
</section> -->

    <!-- Access Cards -->
    <!-- <section class="access-section" id="access">
    <h2 class="section-title">Pilih Akses Anda</h2>
    <p class="section-subtitle">Dua jenis akses untuk kebutuhan yang berbeda</p>
    <div class="access-grid">
        <div class="access-card">
            <div class="access-header">
                <h3><i data-lucide="users" style="width:24px;vertical-align:middle;margin-right:.5rem"></i> Akses Publik</h3>
                <p>Untuk taruna dan pihak eksternal</p>
            </div>
            <div class="access-body">
                <ul>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Berikan saran dan masukan</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Kelola kebutuhan makanan</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Lihat menu harian</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Vote menu favorit</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Laporan kepuasan</li>
                </ul>
                <a href="#public" class="btn btn-primary" style="width:100%;justify-content:center;">Akses Sekarang</a>
            </div>
        </div>
        <div class="access-card">
            <div class="access-header" style="background:linear-gradient(135deg,#1e40af 0%,#3b82f6 100%);">
                <h3><i data-lucide="lock" style="width:24px;vertical-align:middle;margin-right:.5rem"></i> Akses Internal</h3>
                <p>Untuk pengurus dan staf koperasi</p>
            </div>
            <div class="access-body">
                <ul>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Pelaporan keuangan bulanan</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Manajemen inventaris</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Analitik penjualan</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Kelola saran taruna</li>
                    <li><i data-lucide="check-circle" style="width:18px;color:var(--accent)"></i> Export laporan</li>
                </ul>
                <a href="login.php" class="btn btn-primary" style="width:100%;justify-content:center;background:#1e40af;">Login Internal</a>
            </div>
        </div>
    </div>
</section> -->

    <!-- Public Forms -->
    <section class="form-section" id="public">
        <h2 class="section-title">One Stop Services</h2>
        <p class="section-subtitle">Berikan saran dan kelola kebutuhan Anda</p>
        <div class="form-container">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?= ($tab === 'saran') ? 'active' : '' ?>" onclick="switchTab('saran',this)">Saran &amp; Masukan</button>
                <button class="tab <?= ($tab === 'kebutuhan') ? 'active' : '' ?>" onclick="switchTab('kebutuhan',this)">Kelola Kebutuhan</button>
                <button class="tab" onclick="switchTab('menu',this)">Vote Menu</button>
            </div>

            <?php if ($formError): ?>
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" style="width:18px"></i> <?= e($formError) ?>
                </div>
            <?php endif; ?>

            <!-- Tab: Saran -->
            <div class="tab-content <?= ($tab === 'saran') ? 'active' : '' ?>" id="saran">
                <form method="POST" action="index.php#public">
                    <input type="hidden" name="action" value="saran">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nama">Nama Lengkap *</label>
                            <input type="text" id="nama" name="nama" required placeholder="Masukkan nama Anda">
                        </div>
                        <div class="form-group">
                            <label for="nis_nip">Tingkat *</label>
                            <select id="nis_nip" name="nis_nip" required>
                                <option value="">-- Pilih Tingkat --</option>
                                <option value="IV">IV</option>
                                <option value="III">III</option>
                                <option value="II">II</option>
                                <option value="I">I</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="kategori">Kategori Saran *</label>
                        <select id="kategori" name="kategori" required>
                            <option value="">Pilih Kategori</option>
                            <option value="menu">Menu Makanan</option>
                            <option value="pelayanan">Pelayanan</option>
                            <option value="fasilitas">Fasilitas</option>
                            <option value="harga">Harga</option>
                            <option value="kebersihan">Kebersihan</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="saran">Isi Saran / Masukan *</label>
                        <textarea id="saran" name="saran" required placeholder="Tuliskan saran atau masukan Anda di sini..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        <i data-lucide="send" style="width:16px"></i> Kirim Saran
                    </button>
                </form>
            </div>

            <!-- Tab: Kebutuhan -->
            <div class="tab-content <?= ($tab === 'kebutuhan') ? 'active' : '' ?>" id="kebutuhan">
                <form method="POST" action="index.php#public">
                    <input type="hidden" name="action" value="kebutuhan">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="namaK">Nama Lengkap *</label>
                            <input type="text" id="namaK" name="namaK" required placeholder="Masukkan nama Anda">
                        </div>
                        <div class="form-group">
                            <label for="angkatan">Tingkat *</label>
                            <select id="angkatan" name="angkatan" required>
                                <option value="">-- Pilih Tingkat --</option>
                                <option value="IV">IV</option>
                                <option value="III">III</option>
                                <option value="II">II</option>
                                <option value="I">I</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="jenisK">Jenis Kebutuhan *</label>
                        <select id="jenisK" name="jenisK" required>
                            <option value="">Pilih Jenis</option>
                            <option value="makanan">Makanan</option>
                            <option value="minuman">Minuman</option>
                            <option value="snack">Snack</option>
                            <option value="buah">Buah</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="itemK">Nama Item *</label>
                            <input type="text" id="itemK" name="itemK" required placeholder="Contoh: Nasi Goreng">
                        </div>
                        <div class="form-group">
                            <label for="jumlah">Jumlah *</label>
                            <input type="text" id="jumlah" name="jumlah" required placeholder="Contoh: 50 porsi">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="tanggal">Tanggal Dibutuhkan *</label>
                        <input type="date" id="tanggal" name="tanggal" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        <i data-lucide="send" style="width:16px"></i> Ajukan Kebutuhan
                    </button>
                </form>
            </div>

            <!-- Tab: Vote Menu -->
            <div class="tab-content" id="menu">
                <h3 style="margin-bottom:1rem;font-size:1.1rem;">Pilih Menu Favorit Anda</h3>
                <?php if ($sudahVote): ?>
                    <div class="alert alert-success">
                        <i data-lucide="check-circle" style="width:18px"></i>
                        Anda sudah memberikan vote hari ini. Terima kasih!
                    </div>
                <?php endif; ?>
                <form method="POST" action="index.php#public">
                    <input type="hidden" name="action" value="vote">
                    <div class="vote-grid">
                        <?php foreach ($menus as $m): ?>
                            <label class="vote-card" style="cursor:<?= $sudahVote ? 'default' : 'pointer' ?>">
                                <input type="radio" name="menu_id" value="<?= $m['id'] ?>" style="display:none"
                                    <?= $sudahVote ? 'disabled' : '' ?>>
                                <h4><?= e($m['nama_menu']) ?></h4>
                                <p><?= e($m['deskripsi'] ?? '') ?></p>
                                <span class="vote-count"><?= $m['total_votes'] ?></span>
                                <small style="color:var(--text-gray);">votes</small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$sudahVote): ?>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            <i data-lucide="thumbs-up" style="width:16px"></i> Kirim Vote
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-container">
            <div class="footer-section">
                <h3>Grey Corps Canteen</h3>
                <p style="color:#d1d5db;margin-bottom:1rem;">Platform terintegrasi pengelolaan koperasi kantin modern berbasis digital.</p>
            </div>
            <!-- <div class="footer-section">
            <h3>Layanan</h3>
            <ul class="footer-links">
                <li><a href="#public">Saran & Masukan</a></li>
                <li><a href="#public">Kelola Kebutuhan</a></li>
                <li><a href="#public">Vote Menu</a></li>
                <li><a href="login.php">Akses Internal</a></li>
            </ul>
        </div> -->
            <!-- <div class="footer-section">
            <h3>Informasi</h3>
            <ul class="footer-links">
                <li><a href="#">Tentang Kami</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">Hubungi Kami</a></li>
            </ul>
        </div> -->
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Grey Corps Canteen</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        // Tab switching
        function switchTab(id, el) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            if (el) el.classList.add('active');
        }

        // Vote card selection
        document.querySelectorAll('.vote-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.vote-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type=radio]');
                if (radio) radio.checked = true;
            });
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Auto dismiss flash toast
        const toast = document.getElementById('flashToast');
        if (toast) {
            setTimeout(() => {
                toast.style.animation = 'slideIn .3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }
    </script>

    <script>
        (function() {
            var btn = document.querySelector('.mob-toggle');
            var nav = document.querySelector('.tnav');
            if (btn && nav) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    nav.classList.toggle('open');
                });
                document.addEventListener('click', function(e) {
                    if (!nav.contains(e.target) && !btn.contains(e.target)) {
                        nav.classList.remove('open');
                    }
                });
            }
        })();
    </script>
</body>

</html>