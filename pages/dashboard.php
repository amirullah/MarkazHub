<?php
// Periode laporan (default: tahun ini). Bisa preset atau rentang custom.
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = date('Y-12-31');

$rangeParams = [$from . ' 00:00:00', $to . ' 23:59:59'];
$activeWhere = "WHERE o.order_date BETWEEN ? AND ? AND o.status NOT IN ('CANCELLED','RETURNED')";

$totalStores = (int) scalar('SELECT COUNT(*) FROM stores WHERE active=1');
$grandTotal  = (int) scalar('SELECT COUNT(*) FROM orders');

$agg = q1("SELECT COUNT(*) n,
    COALESCE(SUM(product_revenue + other_income),0) rev,
    COALESCE(SUM(cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost),0) cost
   FROM orders o $activeWhere", $rangeParams);
$rev = (float) $agg['rev']; $cost = (float) $agg['cost']; $profit = $rev - $cost;
$margin = $rev > 0 ? $profit / $rev * 100 : 0; $nOrders = (int) $agg['n'];
$aov = $nOrders > 0 ? $rev / $nOrders : 0;

$rc = q1("SELECT SUM(status='RETURNED') ret, SUM(status='CANCELLED') btl
          FROM orders o WHERE o.order_date BETWEEN ? AND ?", $rangeParams);

// Per channel (Shopee vs Tokopedia/TikTok)
$byCh = ['SHOPEE' => ['rev' => 0, 'profit' => 0, 'n' => 0], 'TOKOTIKTOK' => ['rev' => 0, 'profit' => 0, 'n' => 0]];
foreach (q("SELECT o.marketplace,
    COUNT(*) n, COALESCE(SUM(product_revenue+other_income),0) rev,
    COALESCE(SUM(cogs+admin_fee+shipping_cost_seller+voucher_seller_borne+dropship_cost+other_cost),0) cost
   FROM orders o $activeWhere GROUP BY o.marketplace", $rangeParams) as $r) {
    $cg = CHANNEL_OF[$r['marketplace']] ?? 'SHOPEE';
    $byCh[$cg]['rev'] += (float) $r['rev'];
    $byCh[$cg]['profit'] += (float) $r['rev'] - (float) $r['cost'];
    $byCh[$cg]['n'] += (int) $r['n'];
}

// Laba per bulan (dalam rentang)
$byMonth = q("SELECT DATE_FORMAT(o.order_date,'%Y-%m') bln, COUNT(*) n,
    COALESCE(SUM(product_revenue+other_income),0) rev,
    COALESCE(SUM(cogs+admin_fee+shipping_cost_seller+voucher_seller_borne+dropship_cost+other_cost),0) cost
   FROM orders o $activeWhere GROUP BY bln ORDER BY bln DESC", $rangeParams);

$recent = q("SELECT o.*, s.name AS store_name FROM orders o JOIN stores s ON s.id=o.store_id
             ORDER BY o.order_date DESC LIMIT 8");

$importBtn = '<a class="btn btn-primary" href="' . e(url('import')) . '">📥 Import Laporan</a>';
page_header('Dashboard', "Laporan $from s/d $to · $totalStores toko aktif · $grandTotal total pesanan.", $importBtn);

$bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$presets = [
    'Bulan ini'  => [date('Y-m-01'), date('Y-m-t')],
    'Bulan lalu' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Tahun ini'  => [date('Y-01-01'), date('Y-12-31')],
    'Tahun lalu' => [date('Y-01-01', strtotime('-1 year')), date('Y-12-31', strtotime('-1 year'))],
];
if ($grandTotal === 0): ?>
  <div class="empty">
    <div class="empty-ic">📭</div><h3>Belum ada data pesanan</h3>
    <p class="muted">Tambahkan toko, lalu import laporan dari marketplace.</p>
    <div class="row gap"><a class="btn btn-secondary" href="<?= e(url('stores')) ?>">Tambah Toko</a><a class="btn btn-primary" href="<?= e(url('import')) ?>">Import Laporan</a></div>
  </div>
<?php else: ?>
  <!-- Pemilih periode -->
  <div class="filters">
    <?php foreach ($presets as $lbl => [$pf, $pt]): ?>
      <a class="chip<?= ($from === $pf && $to === $pt) ? ' active' : '' ?>" href="<?= e(url('dashboard', ['from' => $pf, 'to' => $pt])) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
    <form method="get" class="filter-form" style="display:flex;gap:.4rem;align-items:center">
      <input type="hidden" name="p" value="dashboard">
      <label class="inline-lbl">Dari <input type="date" name="from" class="input" value="<?= e($from) ?>"></label>
      <label class="inline-lbl">s/d <input type="date" name="to" class="input" value="<?= e($to) ?>"></label>
      <button class="btn btn-secondary">Terapkan</button>
    </form>
  </div>

  <div class="stat-grid">
    <div class="card stat"><div class="stat-label">Omzet</div><div class="stat-val"><?= rupiah($rev) ?></div><div class="stat-hint">tanpa batal & retur</div></div>
    <div class="card stat"><div class="stat-label">Laba Bersih</div><div class="stat-val <?= $profit >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($profit) ?></div><div class="stat-hint">Margin <?= persen($margin) ?></div></div>
    <div class="card stat"><div class="stat-label">Pesanan</div><div class="stat-val"><?= $nOrders ?></div><div class="stat-hint">Rata2 <?= rupiah($aov) ?>/pesanan</div></div>
    <div class="card stat"><div class="stat-label">Retur / Batal</div><div class="stat-val"><?= (int)$rc['ret'] ?> / <?= (int)$rc['btl'] ?></div><div class="stat-hint">dalam periode ini</div></div>
  </div>

  <div class="two-col">
    <div class="card pad">
      <h2 class="card-title">Laba per Channel</h2>
      <?php foreach ($byCh as $ck => $d): if ($d['n'] === 0) continue; ?>
        <div class="mp-row">
          <div class="row gap"><span class="badge <?= CHANNEL_CLASS[$ck] ?>"><?= e(CHANNEL_LABEL[$ck]) ?></span><span class="muted"><?= $d['n'] ?> pesanan</span></div>
          <div class="right"><div class="<?= $d['profit'] >= 0 ? 'pos' : 'neg' ?> bold"><?= rupiah($d['profit']) ?></div><div class="muted tiny">omzet <?= rupiah($d['rev']) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card pad">
      <h2 class="card-title">Pesanan Terbaru</h2>
      <?php foreach ($recent as $o): $p = hitung_laba($o); ?>
        <a class="recent" href="<?= e(url('order_detail', ['id' => $o['id']])) ?>">
          <div><div class="bold"><?= e($o['external_no']) ?></div><div class="muted tiny"><?= e($o['store_name']) ?> · <?= badge_status($o['status']) ?></div></div>
          <div class="<?= $p['profit'] >= 0 ? 'pos' : 'neg' ?> bold"><?= rupiah($p['profit']) ?></div>
        </a>
      <?php endforeach; ?>
      <a class="link-center" href="<?= e(url('orders')) ?>">Lihat semua pesanan →</a>
    </div>
  </div>

  <div class="card pad" style="margin-top:1.5rem">
    <h2 class="card-title">Laba per Bulan</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Bulan</th><th class="right">Pesanan</th><th class="right">Omzet</th><th class="right">Total Biaya</th><th class="right">Laba</th><th class="right">Margin</th></tr></thead>
        <tbody>
        <?php if (!$byMonth): ?>
          <tr><td colspan="6" class="muted" style="text-align:center;padding:1rem">Tidak ada data pada periode ini.</td></tr>
        <?php else: foreach ($byMonth as $m): $r2 = (float)$m['rev']; $c2 = (float)$m['cost']; $pr = $r2 - $c2; $mg = $r2 > 0 ? $pr / $r2 * 100 : 0;
          [$yy, $mm] = explode('-', $m['bln']); ?>
          <tr>
            <td class="bold"><?= $bulan[(int)$mm] . ' ' . $yy ?></td>
            <td class="right"><?= (int)$m['n'] ?></td>
            <td class="right"><?= rupiah($r2) ?></td>
            <td class="right muted"><?= rupiah($c2) ?></td>
            <td class="right bold <?= $pr >= 0 ? 'pos' : 'neg' ?>"><?= rupiah($pr) ?></td>
            <td class="right muted"><?= persen($mg) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
