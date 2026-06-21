<?php
// Kerangka tampilan: header + sidebar + footer.

function render_header(string $active = 'dashboard'): void
{
    $nav = [
        'dashboard' => ['Dashboard', '📊'],
        'orders'    => ['Pesanan', '🧾'],
        'import'    => ['Import Laporan', '📥'],
        'products'  => ['Produk & HPP', '📦'],
        'stores'    => ['Toko', '🏪'],
        'suppliers' => ['Supplier', '🚚'],
    ];
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MarkazHub — Kelola Penjualan Online</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><span>🛍️</span><div><div class="brand-name">MarkazHub</div><div class="brand-sub">Kelola penjualan online</div></div></div>
    <nav>
      <?php foreach ($nav as $key => [$label, $icon]): ?>
        <a href="<?= e(url($key)) ?>" class="nav-item<?= $active === $key ? ' active' : '' ?>"><span><?= $icon ?></span><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">v1.0 · PHP/MySQL</div>
  </aside>
  <main class="main">
    <div class="container">
      <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
<?php
}

function render_footer(): void
{
    ?>
    </div>
  </main>
</div>
</body>
</html>
<?php
}

// Header halaman + tombol aksi opsional.
function page_header(string $title, string $desc = '', string $actionHtml = ''): void
{
    ?>
    <div class="page-head">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($desc): ?><p class="muted"><?= e($desc) ?></p><?php endif; ?>
      </div>
      <?php if ($actionHtml): ?><div><?= $actionHtml ?></div><?php endif; ?>
    </div>
<?php
}

function badge_marketplace(string $mp): string
{
    return '<span class="badge ' . MARKETPLACE_CLASS[$mp] . '">' . e(MARKETPLACE_LABEL[$mp]) . '</span>';
}

function badge_status(string $st): string
{
    return '<span class="badge ' . STATUS_CLASS[$st] . '">' . e(STATUS_LABEL[$st]) . '</span>';
}

// Badge channel (Shopee / Tokopedia-TikTok digabung).
function badge_channel(string $mp): string
{
    $ch = CHANNEL_OF[$mp] ?? 'SHOPEE';
    return '<span class="badge ' . CHANNEL_CLASS[$ch] . '">' . e(CHANNEL_LABEL[$ch]) . '</span>';
}
