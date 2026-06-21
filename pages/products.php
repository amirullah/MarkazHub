<?php
$qstr = trim($_GET['q'] ?? '');
$LIMIT = 300;
$total = (int) scalar('SELECT COUNT(*) FROM products WHERE active = 1');

$cond = 'WHERE p.active = 1';
$params = [];
if ($qstr !== '') {
    $cond .= ' AND (p.sku LIKE ? OR p.name LIKE ?)';
    $params[] = '%' . $qstr . '%';
    $params[] = '%' . $qstr . '%';
}
$pSortMap = ['sku' => 'p.sku', 'name' => 'p.name', 'hpp' => 'p.cost_price', 'dropship' => 'p.dropship_cost'];
$pSort = isset($_GET['sort'], $pSortMap[$_GET['sort']]) ? $_GET['sort'] : 'name';
$pDir  = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$pCarry = array_filter(['q' => $qstr]);
$products = q("SELECT p.*, s.name AS supplier_name FROM products p
               LEFT JOIN suppliers s ON s.id = p.supplier_id
               $cond ORDER BY {$pSortMap[$pSort]} $pDir LIMIT $LIMIT", $params);
$suppliers = q('SELECT id, name, type FROM suppliers ORDER BY name');
page_header('Produk & HPP', 'Katalog produk + modal (HPP) & biaya dropship Jakmall. SKU dipakai untuk mencocokkan saat import.');
?>
<div class="layout-2-1">
  <div>
    <form method="get" class="search-bar">
      <input type="hidden" name="p" value="products">
      <input type="search" name="q" class="input" value="<?= e($qstr) ?>"
             placeholder="🔎 Cari SKU atau nama produk…" autocomplete="off">
      <button class="btn btn-primary">Cari</button>
      <?php if ($qstr !== ''): ?><a class="btn btn-secondary" href="<?= e(url('products')) ?>">Reset</a><?php endif; ?>
    </form>
    <p class="muted" style="margin:0 0 .75rem">
      <?php if ($qstr !== ''): ?>
        Hasil "<strong><?= e($qstr) ?></strong>": menampilkan <?= count($products) ?><?= count($products) >= $LIMIT ? '+ (dipersempit, ketik lebih spesifik)' : '' ?> dari <?= number_format($total, 0, ',', '.') ?> produk.
      <?php else: ?>
        Menampilkan <?= count($products) ?> dari <?= number_format($total, 0, ',', '.') ?> produk<?= $total > $LIMIT ? ' — gunakan pencarian untuk menemukan produk tertentu' : '' ?>.
      <?php endif; ?>
    </p>
    <?php if (count($products) === 0): ?>
      <div class="card pad muted"><?= $qstr !== '' ? 'Tidak ada produk cocok dengan pencarian.' : 'Belum ada produk. Tambahkan agar HPP otomatis terisi saat import.' ?></div>
    <?php else: ?>
      <div class="card table-wrap">
        <table>
          <thead><tr>
            <?= sort_th('products', 'sku', 'SKU', $pSort, $pDir, $pCarry) ?>
            <?= sort_th('products', 'name', 'Nama', $pSort, $pDir, $pCarry) ?>
            <?= sort_th('products', 'hpp', 'HPP', $pSort, $pDir, $pCarry) ?>
            <?= sort_th('products', 'dropship', 'Dropship', $pSort, $pDir, $pCarry) ?>
            <th>Supplier</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td class="mono tiny"><?= e($p['sku']) ?></td>
              <td class="bold"><?= e($p['name']) ?></td>
              <td><?= rupiah($p['cost_price']) ?></td>
              <td><?= rupiah($p['dropship_cost']) ?></td>
              <td class="muted"><?= e($p['supplier_name'] ?: '—') ?></td>
              <td class="right">
                <form method="post" onsubmit="return confirm('Arsipkan produk ini?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="link-danger">Arsip</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" class="card pad form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create_product">
    <h2 class="card-title">Tambah Produk</h2>
    <div class="grid2">
      <div><label class="label">SKU</label><input name="sku" class="input" placeholder="KAOS-PUTIH-M" required></div>
      <div><label class="label">Nama Produk</label><input name="name" class="input" placeholder="Kaos Putih M" required></div>
    </div>
    <div class="grid2">
      <div><label class="label">HPP / Modal (Rp)</label><input type="number" min="0" name="cost_price" class="input" placeholder="35000"><p class="hint">Untuk stok sendiri.</p></div>
      <div><label class="label">Biaya Dropship Jakmall (Rp)</label><input type="number" min="0" name="dropship_cost" class="input" placeholder="40000"><p class="hint">Harga beli ke Jakmall.</p></div>
    </div>
    <div class="field">
      <label class="label">Supplier (opsional)</label>
      <select name="supplier_id" class="input">
        <option value="">— Tidak ada —</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e(SUPPLIER_TYPE_LABEL[$s['type']]) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary full">Simpan Produk</button>
  </form>
</div>
