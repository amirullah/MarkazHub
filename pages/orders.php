<?php
$mp = $_GET['mp'] ?? '';
$storeFilter = (int) ($_GET['store'] ?? 0);
$qstr = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if (in_array($mp, MARKETPLACES, true)) { $where[] = 'o.marketplace = ?'; $params[] = $mp; }
if ($storeFilter > 0) { $where[] = 'o.store_id = ?'; $params[] = $storeFilter; }
if ($qstr !== '') {
    $where[] = '(o.external_no LIKE ? OR o.buyer_name LIKE ? OR EXISTS
                (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND (i.sku LIKE ? OR i.name LIKE ?)))';
    $like = '%' . $qstr . '%';
    array_push($params, $like, $like, $like, $like);
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
// Pertahankan filter lain saat membuat tautan/aksi.
$keep = array_filter(['mp' => $mp, 'store' => $storeFilter ?: '', 'q' => $qstr], fn($v) => $v !== '' && $v !== 0);

$orders = q("SELECT o.*, s.name AS store_name,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
            FROM orders o JOIN stores s ON s.id = o.store_id
            $wsql ORDER BY o.order_date DESC LIMIT 300", $params);
$stores = q('SELECT id, name FROM stores ORDER BY name');
$t = jumlah_laba($orders);

$importBtn = '<a class="btn btn-primary" href="' . e(url('import')) . '">📥 Import</a>';
page_header('Pesanan', 'Semua pesanan beserta laba per pesanan.', $importBtn);
?>

<form method="get" class="search-bar">
  <input type="hidden" name="p" value="orders">
  <?php if ($mp !== ''): ?><input type="hidden" name="mp" value="<?= e($mp) ?>"><?php endif; ?>
  <?php if ($storeFilter): ?><input type="hidden" name="store" value="<?= $storeFilter ?>"><?php endif; ?>
  <input type="search" name="q" class="input" value="<?= e($qstr) ?>"
         placeholder="🔎 Cari No. Pesanan, pembeli, SKU, atau nama produk…" autocomplete="off">
  <button class="btn btn-primary">Cari</button>
  <?php if ($qstr !== ''): ?><a class="btn btn-secondary" href="<?= e(url('orders', array_diff_key($keep, ['q' => 1]))) ?>">Reset</a><?php endif; ?>
</form>

<div class="filters">
  <a class="chip<?= (!$mp && !$storeFilter) ? ' active' : '' ?>" href="<?= e(url('orders', $qstr !== '' ? ['q' => $qstr] : [])) ?>">Semua</a>
  <?php foreach (MARKETPLACES as $m): ?>
    <a class="chip<?= ($mp === $m && !$storeFilter) ? ' active' : '' ?>" href="<?= e(url('orders', array_merge(['mp' => $m], $qstr !== '' ? ['q' => $qstr] : []))) ?>"><?= e(MARKETPLACE_LABEL[$m]) ?></a>
  <?php endforeach; ?>
  <?php if ($stores): ?>
    <form method="get" class="filter-form">
      <input type="hidden" name="p" value="orders">
      <?php if ($mp !== ''): ?><input type="hidden" name="mp" value="<?= e($mp) ?>"><?php endif; ?>
      <?php if ($qstr !== ''): ?><input type="hidden" name="q" value="<?= e($qstr) ?>"><?php endif; ?>
      <select name="store" class="input" onchange="this.form.submit()">
        <option value="0">Semua toko</option>
        <?php foreach ($stores as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $storeFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>
<?php if ($qstr !== ''): ?>
  <p class="muted" style="margin:.25rem 0 1rem">Hasil pencarian "<strong><?= e($qstr) ?></strong>": <?= count($orders) ?> pesanan<?= count($orders) >= 300 ? ' (300 teratas)' : '' ?>.</p>
<?php endif; ?>

<?php if (count($orders) === 0): ?>
  <div class="empty">
    <div class="empty-ic">📭</div><h3>Belum ada pesanan</h3>
    <p class="muted">Import laporan dari marketplace untuk mulai menghitung keuntungan.</p>
    <a class="btn btn-primary" href="<?= e(url('import')) ?>">Import Laporan</a>
  </div>
<?php else: ?>
  <div class="stat-grid four">
    <div class="card stat"><div class="stat-label">Omzet</div><div class="stat-val"><?= rupiah($t['revenue']) ?></div></div>
    <div class="card stat"><div class="stat-label">Biaya</div><div class="stat-val"><?= rupiah($t['totalCost']) ?></div></div>
    <div class="card stat"><div class="stat-label">Laba</div><div class="stat-val <?= $t['profit'] >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($t['profit']) ?></div><div class="stat-hint">Margin <?= persen($t['margin']) ?></div></div>
    <div class="card stat"><div class="stat-label">Pesanan</div><div class="stat-val"><?= count($orders) ?></div></div>
  </div>

  <div class="card table-wrap">
    <table>
      <thead><tr>
        <th>No. Pesanan</th><th>Tanggal</th><th>Toko</th><th>Pemenuhan</th><th>Status</th>
        <th class="right">Omzet</th><th class="right">Laba</th>
      </tr></thead>
      <tbody>
      <?php foreach ($orders as $o): $p = hitung_laba($o); ?>
        <tr>
          <td><a class="link" href="<?= e(url('order_detail', ['id' => $o['id']])) ?>"><?= e($o['external_no']) ?></a>
            <div class="muted tiny"><?= (int)$o['item_count'] ?> item ·
              <?php if (!empty($o['income_verified'])): ?>
                <span class="net-tag net-ok" title="Laba dari Total Penghasilan Shopee (uang bersih riil)">✓ bersih</span>
              <?php else: ?>
                <span class="net-tag net-est" title="Laba estimasi: biaya admin dari persentase toko (belum ada Laporan Penghasilan)">≈ estimasi</span>
              <?php endif; ?>
            </div></td>
          <td class="muted nowrap"><?= tanggal($o['order_date']) ?></td>
          <td><?= badge_marketplace($o['marketplace']) ?><div class="muted tiny"><?= e($o['store_name']) ?></div></td>
          <td class="muted tiny"><?= e(FULFILLMENT_LABEL[$o['fulfillment']]) ?></td>
          <td><?= badge_status($o['status']) ?></td>
          <td class="right"><?= rupiah($p['revenue']) ?></td>
          <td class="right bold <?= $p['profit'] >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($p['profit']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
