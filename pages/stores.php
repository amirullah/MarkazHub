<?php
$stores = q("SELECT s.*, (SELECT COUNT(*) FROM orders o WHERE o.store_id = s.id) AS order_count
             FROM stores s ORDER BY s.marketplace, s.name");
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? q1('SELECT * FROM stores WHERE id = ?', [$editId]) : null;
page_header('Toko', 'Daftar toko di tiap marketplace. Satu marketplace boleh punya banyak toko.');
?>
<div class="layout-2-1">
  <div>
    <?php if (count($stores) === 0): ?>
      <div class="card pad muted">Belum ada toko. Tambahkan lewat form di samping.</div>
    <?php else: ?>
      <div class="card table-wrap">
        <table>
          <thead><tr><th>Toko</th><th>Marketplace</th><th class="right">Admin %</th><th class="right">Pesanan</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($stores as $s): ?>
            <tr<?= $editId === (int)$s['id'] ? ' class="row-editing"' : '' ?>>
              <td class="bold"><?= e($s['name']) ?><?= !$s['active'] ? ' <span class="muted tiny">(nonaktif)</span>' : '' ?></td>
              <td><?= badge_marketplace($s['marketplace']) ?></td>
              <td class="right"><?= persen($s['default_admin_fee_percent']) ?></td>
              <td class="right"><?= (int)$s['order_count'] ?></td>
              <td class="right nowrap">
                <a class="link" href="<?= e(url('stores', ['edit' => $s['id']])) ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus toko ini?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete_store"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="link-danger" style="margin-left:.5rem">Hapus</button>
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
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update_store' : 'create_store' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <h2 class="card-title"><?= $edit ? '✏️ Edit Toko' : '➕ Tambah Toko' ?></h2>
    <div class="field"><label class="label">Nama Toko</label>
      <input name="name" class="input" placeholder="Toko Berkah Jaya" required value="<?= e($edit['name'] ?? '') ?>"></div>
    <div class="field"><label class="label">Marketplace</label>
      <select name="marketplace" class="input">
        <?php foreach (MARKETPLACES as $m): ?>
          <option value="<?= $m ?>" <?= ($edit['marketplace'] ?? '') === $m ? 'selected' : '' ?>><?= e(MARKETPLACE_LABEL[$m]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="label">Biaya Admin Default (%)</label>
      <input type="number" step="0.1" min="0" name="default_admin_fee_percent" class="input" placeholder="6.5" value="<?= $edit ? e((float)$edit['default_admin_fee_percent']) : '' ?>">
      <p class="hint">Untuk estimasi biaya admin saat file import tidak memuat rincian biaya.</p>
    </div>
    <div class="field"><label class="label">Catatan (opsional)</label><input name="note" class="input" value="<?= e($edit['note'] ?? '') ?>"></div>
    <?php if ($edit): ?>
      <label class="radio-line"><input type="checkbox" name="active" value="1" <?= $edit['active'] ? 'checked' : '' ?>> Toko aktif</label>
    <?php endif; ?>
    <button class="btn btn-primary full" style="margin-top:.5rem"><?= $edit ? 'Simpan Perubahan' : 'Simpan Toko' ?></button>
    <?php if ($edit): ?><a class="btn btn-secondary full" style="margin-top:.5rem" href="<?= e(url('stores')) ?>">Batal</a><?php endif; ?>
  </form>
</div>
