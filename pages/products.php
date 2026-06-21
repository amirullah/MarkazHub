<?php
$products = q('SELECT p.*, s.name AS supplier_name FROM products p
               LEFT JOIN suppliers s ON s.id = p.supplier_id
               WHERE p.active = 1 ORDER BY p.name');
$suppliers = q('SELECT id, name, type FROM suppliers ORDER BY name');
page_header('Produk & HPP', 'Katalog produk + modal (HPP) & biaya dropship Jakmall. SKU dipakai untuk mencocokkan saat import.');
?>
<div class="layout-2-1">
  <div>
    <?php if (count($products) === 0): ?>
      <div class="card pad muted">Belum ada produk. Tambahkan agar HPP otomatis terisi saat import.</div>
    <?php else: ?>
      <div class="card table-wrap">
        <table>
          <thead><tr><th>SKU</th><th>Nama</th><th>HPP</th><th>Dropship</th><th>Supplier</th><th></th></tr></thead>
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
