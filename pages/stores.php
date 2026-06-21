<?php
$stores = q("SELECT s.*, (SELECT COUNT(*) FROM orders o WHERE o.store_id = s.id) AS order_count
             FROM stores s ORDER BY s.marketplace, s.name");
page_header('Toko', 'Daftar toko di tiap marketplace. Satu marketplace boleh punya banyak toko.');
?>
<div class="layout-2-1">
  <div>
    <?php if (count($stores) === 0): ?>
      <div class="card pad muted">Belum ada toko. Tambahkan lewat form di samping.</div>
    <?php else: ?>
      <div class="card table-wrap">
        <table>
          <thead><tr><th>Toko</th><th>Marketplace</th><th>Admin %</th><th>Pesanan</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($stores as $s): ?>
            <tr>
              <td class="bold"><?= e($s['name']) ?></td>
              <td><?= badge_marketplace($s['marketplace']) ?></td>
              <td><?= persen($s['default_admin_fee_percent']) ?></td>
              <td><?= (int)$s['order_count'] ?></td>
              <td class="right">
                <form method="post" onsubmit="return confirm('Hapus toko ini?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete_store"><input type="hidden" name="id" value="<?= $s['id'] ?>">
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
    <?= csrf_field() ?><input type="hidden" name="action" value="create_store">
    <h2 class="card-title">Tambah Toko</h2>
    <div class="field"><label class="label">Nama Toko</label><input name="name" class="input" placeholder="Toko Berkah Jaya" required></div>
    <div class="field"><label class="label">Marketplace</label>
      <select name="marketplace" class="input">
        <?php foreach (MARKETPLACES as $m): ?><option value="<?= $m ?>"><?= e(MARKETPLACE_LABEL[$m]) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="label">Biaya Admin Default (%)</label>
      <input type="number" step="0.1" min="0" name="default_admin_fee_percent" class="input" placeholder="6.5">
      <p class="hint">Untuk estimasi biaya admin saat file import tidak memuat rincian biaya.</p>
    </div>
    <div class="field"><label class="label">Catatan (opsional)</label><input name="note" class="input"></div>
    <button class="btn btn-primary full">Simpan Toko</button>
  </form>
</div>
