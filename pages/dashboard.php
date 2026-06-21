<?php
$orders = q("SELECT o.*, s.name AS store_name FROM orders o
             JOIN stores s ON s.id = o.store_id
             WHERE o.status NOT IN ('CANCELLED','RETURNED')
             ORDER BY o.order_date DESC");
$totalStores = (int) scalar('SELECT COUNT(*) FROM stores');
$t = jumlah_laba($orders);

// Rekap per marketplace
$byMp = [];
foreach ($orders as $o) {
    $byMp[$o['marketplace']][] = $o;
}

$importBtn = '<a class="btn btn-primary" href="' . e(url('import')) . '">📥 Import Laporan</a>';
page_header('Dashboard', "Ringkasan dari " . count($orders) . " pesanan aktif di $totalStores toko.", $importBtn);

if (count($orders) === 0): ?>
  <div class="empty">
    <div class="empty-ic">📭</div>
    <h3>Belum ada data pesanan</h3>
    <p class="muted">Tambahkan toko, lalu import laporan pesanan dari marketplace untuk melihat perhitungan keuntungan.</p>
    <div class="row gap">
      <a class="btn btn-secondary" href="<?= e(url('stores')) ?>">Tambah Toko</a>
      <a class="btn btn-primary" href="<?= e(url('import')) ?>">Import Laporan</a>
    </div>
  </div>
<?php else: ?>
  <div class="stat-grid">
    <div class="card stat"><div class="stat-label">Pendapatan (Omzet)</div><div class="stat-val"><?= rupiah($t['revenue']) ?></div><div class="stat-hint">Harga produk + pendapatan lain</div></div>
    <div class="card stat"><div class="stat-label">Total Biaya</div><div class="stat-val"><?= rupiah($t['totalCost']) ?></div><div class="stat-hint">Modal + admin + ongkir + dropship + lain</div></div>
    <div class="card stat"><div class="stat-label">Laba Bersih</div><div class="stat-val <?= $t['profit'] >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($t['profit']) ?></div><div class="stat-hint">Margin <?= persen($t['margin']) ?></div></div>
    <div class="card stat"><div class="stat-label">Jumlah Pesanan</div><div class="stat-val"><?= count($orders) ?></div><div class="stat-hint">Tidak termasuk batal/retur</div></div>
  </div>

  <div class="two-col">
    <div class="card pad">
      <h2 class="card-title">Laba per Marketplace</h2>
      <?php foreach ($byMp as $mp => $list): $mt = jumlah_laba($list); ?>
        <div class="mp-row">
          <div class="row gap"><?= badge_marketplace($mp) ?><span class="muted"><?= count($list) ?> pesanan</span></div>
          <div class="right">
            <div class="pos bold"><?= rupiah($mt['profit']) ?></div>
            <div class="muted tiny">omzet <?= rupiah($mt['revenue']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card pad">
      <h2 class="card-title">Pesanan Terbaru</h2>
      <?php foreach (array_slice($orders, 0, 8) as $o): $p = hitung_laba($o); ?>
        <a class="recent" href="<?= e(url('order_detail', ['id' => $o['id']])) ?>">
          <div>
            <div class="bold"><?= e($o['external_no']) ?></div>
            <div class="muted tiny"><?= e($o['store_name']) ?> · <?= badge_status($o['status']) ?></div>
          </div>
          <div class="<?= $p['profit'] >= 0 ? 'pos' : 'neg' ?> bold"><?= rupiah($p['profit']) ?></div>
        </a>
      <?php endforeach; ?>
      <a class="link-center" href="<?= e(url('orders')) ?>">Lihat semua pesanan →</a>
    </div>
  </div>
<?php endif; ?>
