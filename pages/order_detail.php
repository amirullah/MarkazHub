<?php
$id = (int) ($_GET['id'] ?? 0);
$order = q1('SELECT o.*, s.name AS store_name FROM orders o JOIN stores s ON s.id = o.store_id WHERE o.id = ?', [$id]);
if (!$order) {
    echo '<div class="empty"><h3>Pesanan tidak ditemukan</h3><a class="btn btn-secondary" href="' . e(url('orders')) . '">Kembali</a></div>';
    return;
}
$items = q('SELECT * FROM order_items WHERE order_id = ?', [$id]);

$incomeFields = [
    'product_revenue' => 'Harga Produk',
    'shipping_charged_to_buyer' => 'Ongkir Dibayar Pembeli',
    'other_income' => 'Pendapatan Lain',
];
$costFields = [
    'cogs' => 'HPP / Modal',
    'admin_fee' => 'Biaya Admin Marketplace',
    'shipping_cost_seller' => 'Ongkir Ditanggung Penjual',
    'voucher_seller_borne' => 'Voucher Ditanggung Penjual',
    'dropship_cost' => 'Biaya Dropship (Jakmall)',
    'other_cost' => 'Biaya Lain',
];
?>
<a class="link" href="<?= e(url('orders')) ?>">← Kembali ke daftar pesanan</a>
<?php
$delBtn = '<form method="post" onsubmit="return confirm(\'Hapus pesanan ini permanen?\')" style="display:inline">'
    . csrf_field() . '<input type="hidden" name="action" value="delete_order"><input type="hidden" name="id" value="' . $id . '">'
    . '<button class="link-danger">Hapus</button></form>';
page_header('Pesanan ' . $order['external_no'],
    $order['store_name'] . ' · ' . tanggal($order['order_date']) . ($order['buyer_name'] ? ' · ' . $order['buyer_name'] : ''),
    badge_marketplace($order['marketplace']) . ' ' . $delBtn);
?>

<div class="card table-wrap" style="margin-bottom:1.5rem">
  <table>
    <thead><tr><th>Produk</th><th>SKU</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">HPP</th><th class="right">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td class="bold"><?= e($it['name']) ?></td>
        <td class="muted mono tiny"><?= e($it['sku'] ?: '—') ?><?= ($it['sku'] && !$it['product_id']) ? ' <span title="SKU belum di katalog">⚠️</span>' : '' ?></td>
        <td class="right"><?= (int)$it['qty'] ?></td>
        <td class="right"><?= rupiah($it['unit_price']) ?></td>
        <td class="right"><?= rupiah($it['unit_cost']) ?></td>
        <td class="right bold"><?= rupiah($it['unit_price'] * $it['qty']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" class="order-edit">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update_order">
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="oe-main">
    <div class="card pad">
      <div class="grid2">
        <div>
          <label class="label">Status</label>
          <select name="status" class="input">
            <?php foreach (ORDER_STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= e(STATUS_LABEL[$st]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label">Jenis Pemenuhan</label>
          <select name="fulfillment" class="input">
            <?php foreach (FULFILLMENTS as $f): ?>
              <option value="<?= $f ?>" <?= $order['fulfillment'] === $f ? 'selected' : '' ?>><?= e(FULFILLMENT_LABEL[$f]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card pad">
      <h3 class="sec pos-h">Pendapatan</h3>
      <div class="grid3">
        <?php foreach ($incomeFields as $k => $label): ?>
          <div><label class="label"><?= e($label) ?></label>
            <input type="number" min="0" step="any" name="<?= $k ?>" class="input calc inc" value="<?= e((float)$order[$k]) ?>"></div>
        <?php endforeach; ?>
      </div>
      <h3 class="sec neg-h">Biaya</h3>
      <div class="grid3">
        <?php foreach ($costFields as $k => $label): ?>
          <div><label class="label"><?= e($label) ?></label>
            <input type="number" min="0" step="any" name="<?= $k ?>" class="input calc cost" value="<?= e((float)$order[$k]) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:1rem">
        <label class="label">Catatan</label>
        <textarea name="note" class="input" rows="2"><?= e($order['note']) ?></textarea>
      </div>
    </div>
  </div>

  <div class="oe-side">
    <div class="card pad sticky">
      <h3 class="card-title">Ringkasan Laba</h3>
      <div class="sum-row"><span class="muted">Pendapatan</span><span id="sum-rev">-</span></div>
      <div class="sum-row"><span class="muted">Total Biaya</span><span id="sum-cost" class="neg">-</span></div>
      <hr>
      <div class="sum-row big"><span class="bold">Laba Bersih</span><span id="sum-profit" class="bold">-</span></div>
      <div class="right muted tiny" id="sum-margin"></div>
      <button class="btn btn-primary full" style="margin-top:1rem">Simpan Perubahan</button>
    </div>
  </div>
</form>

<script>
(function () {
  function fmt(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
  }
  function recalc() {
    var inc = 0, cost = 0;
    document.querySelectorAll('.inc').forEach(function (el) { inc += parseFloat(el.value) || 0; });
    document.querySelectorAll('.cost').forEach(function (el) { cost += parseFloat(el.value) || 0; });
    // ongkir dibayar pembeli bersifat pass-through -> tidak masuk laba
    var ship = parseFloat(document.querySelector('[name=shipping_charged_to_buyer]').value) || 0;
    var revenue = inc - ship;
    var profit = revenue - cost;
    document.getElementById('sum-rev').textContent = fmt(revenue);
    document.getElementById('sum-cost').textContent = '-' + fmt(cost);
    var pe = document.getElementById('sum-profit');
    pe.textContent = fmt(profit);
    pe.className = 'bold ' + (profit >= 0 ? 'pos' : 'neg');
    var margin = revenue > 0 ? (profit / revenue * 100) : 0;
    document.getElementById('sum-margin').textContent = 'Margin ' + margin.toFixed(1).replace('.', ',') + '%';
  }
  document.querySelectorAll('.calc').forEach(function (el) { el.addEventListener('input', recalc); });
  recalc();
})();
</script>
