<?php
$ch          = $_GET['ch'] ?? '';                 // channel: SHOPEE | TOKOTIKTOK
$storeFilter = (int) ($_GET['store'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$qstr        = trim($_GET['q'] ?? '');
$needFilter  = ($_GET['need'] ?? '') === '1';
$unfinalFilter = ($_GET['unfinal'] ?? '') === '1';
$from        = trim($_GET['from'] ?? '');
$to          = trim($_GET['to'] ?? '');
$page        = max(1, (int) ($_GET['page'] ?? 1));
$perPage     = 50;

// Pengurutan (whitelist kolom agar aman dari injeksi).
$profitExpr = '((o.product_revenue + o.other_income) - (o.cogs + o.admin_fee + o.shipping_cost_seller + o.voucher_seller_borne + o.dropship_cost + o.other_cost))';
$sortMap = [
    'date'  => 'o.order_date',
    'no'    => 'o.external_no',
    'omzet' => '(o.product_revenue + o.other_income)',
    'laba'  => $profitExpr,
];
$sort = isset($_GET['sort'], $sortMap[$_GET['sort']]) ? $_GET['sort'] : 'date';
$dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$orderBy = $sortMap[$sort] . ' ' . $dir . ', o.id ' . $dir;

$needSql = "(o.status NOT IN ('CANCELLED','RETURNED') AND
             ((SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) = 0
              OR EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND i.qty_assumed = 1)))";
// "Belum final" = biaya marketplace belum ada (Laporan Penghasilan belum diimpor),
// di luar pesanan batal/retur.
$unfinalSql = "(o.income_verified = 0 AND o.status NOT IN ('CANCELLED','RETURNED'))";

$where = []; $params = [];
if (isset(CHANNEL_MARKETPLACES[$ch])) {
    $mks = CHANNEL_MARKETPLACES[$ch];
    $where[] = 'o.marketplace IN (' . implode(',', array_fill(0, count($mks), '?')) . ')';
    array_push($params, ...$mks);
}
if ($storeFilter > 0) { $where[] = 'o.store_id = ?'; $params[] = $storeFilter; }
if (in_array($statusFilter, ORDER_STATUSES, true)) { $where[] = 'o.status = ?'; $params[] = $statusFilter; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'o.order_date >= ?'; $params[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'o.order_date <= ?'; $params[] = $to . ' 23:59:59'; }
if ($qstr !== '') {
    $where[] = '(o.external_no LIKE ? OR o.buyer_name LIKE ? OR EXISTS
                (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND (i.sku LIKE ? OR i.name LIKE ?)))';
    $like = '%' . $qstr . '%'; array_push($params, $like, $like, $like, $like);
}
if ($needFilter) { $where[] = $needSql; }
if ($unfinalFilter) { $where[] = $unfinalSql; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Filter aktif untuk dipertahankan di tautan (chip/pagination).
$carry = array_filter([
    'ch' => $ch, 'store' => $storeFilter ?: '', 'status' => $statusFilter,
    'q' => $qstr, 'need' => $needFilter ? '1' : '', 'unfinal' => $unfinalFilter ? '1' : '', 'from' => $from, 'to' => $to,
    'sort' => $sort !== 'date' ? $sort : '', 'dir' => strtolower($dir) !== 'desc' ? strtolower($dir) : '',
], fn($v) => $v !== '' && $v !== 0);

$totalRows = (int) scalar("SELECT COUNT(*) FROM orders o $wsql", $params);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Ringkasan: tidak termasuk batal & retur (kecuali bila difilter ke status itu).
$aggWhere = $where; $aggParams = $params;
if (!in_array($statusFilter, ['CANCELLED', 'RETURNED'], true)) {
    $aggWhere[] = "o.status NOT IN ('CANCELLED','RETURNED')";
}
$aggWsql = $aggWhere ? ('WHERE ' . implode(' AND ', $aggWhere)) : '';
$agg = q1("SELECT COUNT(*) n,
            COALESCE(SUM(product_revenue + other_income),0) rev,
            COALESCE(SUM(cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost),0) cost
           FROM orders o $aggWsql", $aggParams);
$sumRev = (float) $agg['rev']; $sumCost = (float) $agg['cost'];
$sumProfit = $sumRev - $sumCost; $sumMargin = $sumRev > 0 ? $sumProfit / $sumRev * 100 : 0;
// Hitung retur & batal dalam lingkup filter.
$retCancel = q1("SELECT
    SUM(status='RETURNED') ret, SUM(status='CANCELLED') btl
   FROM orders o $wsql", $params);

$orders = q("SELECT o.*, s.name AS store_name,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.qty_assumed = 1) AS assumed_count
            FROM orders o JOIN stores s ON s.id = o.store_id
            $wsql ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);

$stores = q('SELECT id, name, marketplace FROM stores ORDER BY name');
$importBtn = '<a class="btn btn-primary" href="' . e(url('import')) . '">📥 Import</a>';
page_header('Pesanan', "$totalRows pesanan (sesuai filter).", $importBtn);

// Preset tanggal (pakai jam sistem).
$presets = [
    'Bulan ini'  => [date('Y-m-01'), date('Y-m-t')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Tahun ini'  => [date('Y-01-01'), date('Y-12-31')],
];
?>

<form method="get" class="search-bar">
  <input type="hidden" name="p" value="orders">
  <?php foreach (['ch', 'store', 'status', 'from', 'to'] as $k): if (!empty($carry[$k])): ?>
    <input type="hidden" name="<?= $k ?>" value="<?= e($carry[$k]) ?>"><?php endif; endforeach; ?>
  <input type="search" name="q" class="input" value="<?= e($qstr) ?>"
         placeholder="🔎 Cari No. Pesanan, pembeli, SKU, atau nama produk…" autocomplete="off">
  <button class="btn btn-primary">Cari</button>
  <?php if ($qstr !== ''): ?><a class="btn btn-secondary" href="<?= e(url('orders', array_diff_key($carry, ['q' => 1]))) ?>">×</a><?php endif; ?>
</form>

<!-- Filter -->
<div class="filters">
  <?php $chCarry = array_diff_key($carry, ['ch' => 1, 'page' => 1]); ?>
  <a class="chip<?= $ch === '' ? ' active' : '' ?>" href="<?= e(url('orders', $chCarry)) ?>">Semua channel</a>
  <?php foreach (CHANNEL_LABEL as $ck => $cl): ?>
    <a class="chip<?= $ch === $ck ? ' active' : '' ?>" href="<?= e(url('orders', array_merge($chCarry, ['ch' => $ck]))) ?>"><?= e($cl) ?></a>
  <?php endforeach; ?>
  <?php $needCarry = array_diff_key($carry, ['need' => 1, 'page' => 1]); ?>
  <a class="chip chip-need<?= $needFilter ? ' active' : '' ?>" href="<?= e(url('orders', $needFilter ? $needCarry : array_merge($needCarry, ['need' => '1']))) ?>" title="File Pesanan (Order Completed/Pesanan Selesai) belum diimpor → produk/SKU/qty belum lengkap">📦 Belum ada file Pesanan</a>
  <?php $ufCarry = array_diff_key($carry, ['unfinal' => 1, 'page' => 1]); ?>
  <a class="chip chip-need<?= $unfinalFilter ? ' active' : '' ?>" href="<?= e(url('orders', $unfinalFilter ? $ufCarry : array_merge($ufCarry, ['unfinal' => '1']))) ?>" title="Laporan Penghasilan belum diimpor → biaya marketplace belum dihitung, laba belum final">💰 Belum ada Laporan Penghasilan</a>
</div>

<form method="get" class="filter-row">
  <input type="hidden" name="p" value="orders">
  <?php foreach (['ch', 'q', 'need'] as $k): if (!empty($carry[$k])): ?>
    <input type="hidden" name="<?= $k ?>" value="<?= e($carry[$k]) ?>"><?php endif; endforeach; ?>
  <select name="store" class="input">
    <option value="">Semua toko</option>
    <?php foreach ($stores as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $storeFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="input">
    <option value="">Semua status</option>
    <?php foreach (ORDER_STATUSES as $st): ?>
      <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e(STATUS_LABEL[$st]) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="inline-lbl">Dari <input type="date" name="from" class="input" value="<?= e($from) ?>"></label>
  <label class="inline-lbl">s/d <input type="date" name="to" class="input" value="<?= e($to) ?>"></label>
  <button class="btn btn-primary">Terapkan</button>
  <?php if ($carry): ?><a class="btn btn-secondary" href="<?= e(url('orders')) ?>">Reset</a><?php endif; ?>
</form>
<div class="filters" style="margin-top:-.4rem">
  <span class="muted tiny">Cepat:</span>
  <?php foreach ($presets as $lbl => [$pf, $pt]): ?>
    <a class="chip<?= ($from === $pf && $to === $pt) ? ' active' : '' ?>" href="<?= e(url('orders', array_merge(array_diff_key($carry, ['from' => 1, 'to' => 1, 'page' => 1]), ['from' => $pf, 'to' => $pt]))) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($totalRows === 0): ?>
  <div class="empty">
    <div class="empty-ic">📭</div><h3>Tidak ada pesanan sesuai filter</h3>
    <a class="btn btn-secondary" href="<?= e(url('orders')) ?>">Reset filter</a>
  </div>
<?php else: ?>
  <div class="stat-grid four">
    <div class="card stat"><div class="stat-label">Omzet</div><div class="stat-val"><?= rupiah($sumRev) ?></div><div class="stat-hint">tanpa batal & retur</div></div>
    <div class="card stat"><div class="stat-label">Total Biaya</div><div class="stat-val"><?= rupiah($sumCost) ?></div></div>
    <div class="card stat"><div class="stat-label">Laba Bersih</div><div class="stat-val <?= $sumProfit >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($sumProfit) ?></div><div class="stat-hint">Margin <?= persen($sumMargin) ?></div></div>
    <div class="card stat"><div class="stat-label">Pesanan</div><div class="stat-val"><?= (int)$agg['n'] ?></div><div class="stat-hint"><?= (int)$retCancel['ret'] ?> retur · <?= (int)$retCancel['btl'] ?> batal</div></div>
  </div>

  <div class="card table-wrap">
    <table>
      <thead><tr>
        <?= sort_th('orders', 'no', 'No. Pesanan', $sort, $dir, $carry) ?>
        <?= sort_th('orders', 'date', 'Tanggal', $sort, $dir, $carry) ?>
        <th>Toko</th><th>Pemenuhan</th><th>Status</th>
        <?= sort_th('orders', 'omzet', 'Omzet', $sort, $dir, $carry, true) ?>
        <?= sort_th('orders', 'laba', 'Laba', $sort, $dir, $carry, true) ?>
      </tr></thead>
      <tbody>
      <?php foreach ($orders as $o): $p = hitung_laba($o);
        $needsFile = ((int)$o['item_count'] === 0) || ((int)$o['assumed_count'] > 0);
        $fileLbl = $o['marketplace'] === 'SHOPEE' ? 'Order Completed' : 'Pesanan Selesai'; ?>
        <tr>
          <?php $terminal = in_array($o['status'], ['CANCELLED'], true); ?>
          <td><a class="link" href="<?= e(url('order_detail', ['id' => $o['id']])) ?>"><?= e($o['external_no']) ?></a>
            <div class="muted tiny"><?= (int)$o['item_count'] ?> item<?php if (!$terminal): ?> ·
              <?= !empty($o['income_verified'])
                ? '<span class="net-tag net-ok" title="Laba final dari Laporan Penghasilan (uang bersih riil)">✓ laba final</span>'
                : '<span class="net-tag net-est" title="Laporan Penghasilan pesanan ini belum diimpor → biaya marketplace belum dihitung, laba belum final">💰 belum ada Laporan Penghasilan</span>' ?>
            <?php endif; ?></div>
            <?php if ($needsFile && !$terminal && $o['status'] !== 'RETURNED'): ?><div class="tiny"><span class="net-tag tag-need" title="File Pesanan (<?= e($fileLbl) ?>) belum diimpor → produk/SKU/qty belum lengkap">📦 belum ada file Pesanan</span></div><?php endif; ?>
          </td>
          <td class="muted nowrap"><?= tanggal($o['order_date']) ?></td>
          <td><?= badge_channel($o['marketplace']) ?><div class="muted tiny"><?= e($o['store_name']) ?></div></td>
          <td class="muted tiny"><?= e(FULFILLMENT_LABEL[$o['fulfillment']]) ?></td>
          <td><?= badge_status($o['status']) ?></td>
          <td class="right"><?= rupiah($p['revenue']) ?></td>
          <td class="right bold <?= $p['profit'] >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($p['profit']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pager">
      <?php $pc = fn($n) => e(url('orders', array_merge($carry, ['page' => $n]))); ?>
      <a class="btn btn-secondary<?= $page <= 1 ? ' disabled' : '' ?>" <?= $page > 1 ? 'href="' . $pc($page - 1) . '"' : '' ?>>← Sebelumnya</a>
      <span class="muted">Halaman <?= $page ?> dari <?= $totalPages ?></span>
      <a class="btn btn-secondary<?= $page >= $totalPages ? ' disabled' : '' ?>" <?= $page < $totalPages ? 'href="' . $pc($page + 1) . '"' : '' ?>>Berikutnya →</a>
    </div>
  <?php endif; ?>
<?php endif; ?>
