<?php
// ============================================================
// MarkazHub (PHP + MySQL) - front controller / router.
// Routing lewat query string (index.php?p=halaman) agar kompatibel
// dengan semua shared hosting tanpa bergantung pada mod_rewrite.
// ============================================================

session_start();

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/marketplace.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/actions.php';

date_default_timezone_set(config()['timezone'] ?? 'Asia/Jakarta');

// Proses aksi POST lebih dulu (akan redirect & exit).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post();
    exit;
}

$page = preg_replace('/[^a-z_]/', '', $_GET['p'] ?? 'dashboard');
$allowed = ['dashboard', 'orders', 'order_detail', 'import', 'products', 'stores', 'suppliers'];
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

$file = __DIR__ . "/pages/$page.php";
if (!file_exists($file)) {
    $page = 'dashboard';
    $file = __DIR__ . '/pages/dashboard.php';
}

render_header($page === 'order_detail' ? 'orders' : $page);
require $file;
render_footer();
