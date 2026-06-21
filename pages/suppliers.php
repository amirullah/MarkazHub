<?php
$suppliers = q("SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count
                FROM suppliers s ORDER BY s.name");
page_header('Supplier', 'Sumber barang. Untuk dropship, buat supplier bertipe Jakmall.');
?>
<div class="layout-2-1">
  <div>
    <?php if (count($suppliers) === 0): ?>
      <div class="card pad muted">Belum ada supplier.</div>
    <?php else: ?>
      <div class="card table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>Tipe</th><th>Produk</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($suppliers as $s): ?>
            <tr>
              <td class="bold"><?= e($s['name']) ?></td>
              <td><?= e(SUPPLIER_TYPE_LABEL[$s['type']]) ?></td>
              <td><?= (int)$s['product_count'] ?></td>
              <td class="right">
                <form method="post" onsubmit="return confirm('Hapus supplier ini?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete_supplier"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="link-danger">Hapus</button>
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
    <?= csrf_field() ?><input type="hidden" name="action" value="create_supplier">
    <h2 class="card-title">Tambah Supplier</h2>
    <div class="field"><label class="label">Nama Supplier</label><input name="name" class="input" placeholder="Jakmall" required></div>
    <div class="field"><label class="label">Tipe</label>
      <select name="type" class="input">
        <?php foreach (SUPPLIER_TYPES as $t): ?><option value="<?= $t ?>" <?= $t === 'JAKMALL' ? 'selected' : '' ?>><?= e(SUPPLIER_TYPE_LABEL[$t]) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="label">Catatan (opsional)</label><input name="note" class="input"></div>
    <button class="btn btn-primary full">Simpan Supplier</button>
  </form>
</div>
