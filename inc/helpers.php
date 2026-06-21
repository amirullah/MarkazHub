<?php
// Helper umum: escaping, format uang, perhitungan laba, label, CSRF, flash.

// Escape output HTML.
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rupiah($v): string
{
    return 'Rp ' . number_format((float) $v, 0, ',', '.');
}

function persen($v): string
{
    return rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',') . '%';
}

function tanggal($v): string
{
    if (!$v) return '-';
    $ts = strtotime($v);
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

// URL helper (query-string routing agar aman di semua shared hosting).
function url(string $page, array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

// ---- Perhitungan laba (sumber kebenaran tunggal) ----
// revenue   = harga produk + pendapatan lain (ongkir pembeli = pass-through)
// totalCost = HPP + admin + ongkir penjual + voucher + dropship + lain
// profit    = revenue - totalCost
function hitung_laba(array $o): array
{
    $f = fn($k) => (float) ($o[$k] ?? 0);
    $revenue = $f('product_revenue') + $f('other_income');
    $totalCost = $f('cogs') + $f('admin_fee') + $f('shipping_cost_seller')
        + $f('voucher_seller_borne') + $f('dropship_cost') + $f('other_cost');
    $profit = $revenue - $totalCost;
    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
    return [
        'revenue'   => $revenue,
        'totalCost' => $totalCost,
        'profit'    => $profit,
        'margin'    => $margin,
    ];
}

// Jumlahkan laba dari banyak pesanan.
function jumlah_laba(array $orders): array
{
    $acc = ['revenue' => 0, 'totalCost' => 0, 'profit' => 0, 'margin' => 0];
    foreach ($orders as $o) {
        $p = hitung_laba($o);
        $acc['revenue']   += $p['revenue'];
        $acc['totalCost'] += $p['totalCost'];
        $acc['profit']    += $p['profit'];
    }
    $acc['margin'] = $acc['revenue'] > 0 ? ($acc['profit'] / $acc['revenue']) * 100 : 0;
    return $acc;
}

// ---- Label Bahasa Indonesia ----
const MARKETPLACE_LABEL = ['SHOPEE' => 'Shopee', 'TOKOPEDIA' => 'Tokopedia', 'TIKTOK' => 'TikTok Shop'];
const MARKETPLACE_CLASS = ['SHOPEE' => 'mp-shopee', 'TOKOPEDIA' => 'mp-tokopedia', 'TIKTOK' => 'mp-tiktok'];
const FULFILLMENT_LABEL = ['SELF' => 'Packing Sendiri', 'DROPSHIP' => 'Dropship (Jakmall)'];
const STATUS_LABEL = [
    'PENDING' => 'Menunggu', 'PAID' => 'Dibayar', 'SHIPPED' => 'Dikirim',
    'COMPLETED' => 'Selesai', 'CANCELLED' => 'Dibatalkan', 'RETURNED' => 'Dikembalikan',
];
const STATUS_CLASS = [
    'PENDING' => 's-pending', 'PAID' => 's-paid', 'SHIPPED' => 's-shipped',
    'COMPLETED' => 's-completed', 'CANCELLED' => 's-cancelled', 'RETURNED' => 's-cancelled',
];
const SUPPLIER_TYPE_LABEL = ['SELF' => 'Stok Sendiri', 'JAKMALL' => 'Jakmall (Dropship)', 'OTHER' => 'Lainnya'];

// Channel = pengelompokan marketplace untuk tampilan/filter. Tokopedia & TikTok
// berbagi Seller Center, jadi digabung jadi satu channel.
const CHANNEL_OF = ['SHOPEE' => 'SHOPEE', 'TOKOPEDIA' => 'TOKOTIKTOK', 'TIKTOK' => 'TOKOTIKTOK'];
const CHANNEL_LABEL = ['SHOPEE' => 'Shopee', 'TOKOTIKTOK' => 'Tokopedia/TikTok'];
const CHANNEL_CLASS = ['SHOPEE' => 'mp-shopee', 'TOKOTIKTOK' => 'mp-tokopedia'];
const CHANNEL_MARKETPLACES = ['SHOPEE' => ['SHOPEE'], 'TOKOTIKTOK' => ['TOKOPEDIA', 'TIKTOK']];

const MARKETPLACES = ['SHOPEE', 'TOKOPEDIA', 'TIKTOK'];
const FULFILLMENTS = ['SELF', 'DROPSHIP'];
const ORDER_STATUSES = ['PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED', 'RETURNED'];
const SUPPLIER_TYPES = ['SELF', 'JAKMALL', 'OTHER'];

// ---- CSRF sederhana ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(400);
        exit('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

// ---- Flash message (lewat session) ----
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
