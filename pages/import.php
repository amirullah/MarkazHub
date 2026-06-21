<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan',
    'Unggah langsung file ekspor dari Shopee & Jakmall (.xlsx) — tanpa template khusus. Boleh beberapa file sekaligus; sistem mengenali jenis tiap file otomatis.');
?>
<div class="two-col">
  <div class="card pad">
    <?php if (count($stores) === 0): ?>
      <p class="muted">Tambah minimal satu <a class="link" href="<?= e(url('stores')) ?>">toko</a> dulu sebelum import pesanan. (Master Produk Jakmall boleh diunggah tanpa toko.)</p>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_orders">
        <div class="field">
          <label class="label">Toko Tujuan <span class="muted">(untuk file pesanan)</span></label>
          <select name="store_id" class="input">
            <option value="">— Pilih toko —</option>
            <?php foreach ($stores as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> · <?= e(MARKETPLACE_LABEL[$s['marketplace']]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="label">Jenis Pemenuhan untuk pesanan ini</label>
          <select name="fulfillment" class="input">
            <?php foreach (FULFILLMENTS as $f): ?>
              <option value="<?= $f ?>"><?= e(FULFILLMENT_LABEL[$f]) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Dropship memakai biaya beli Jakmall sebagai modal; packing sendiri memakai HPP produk.</p>
        </div>
        <div class="field">
          <label class="label">File (.xlsx / .csv) — boleh pilih beberapa</label>
          <input type="file" name="files[]" accept=".xlsx,.csv" multiple required class="input">
          <p class="hint">Mis. pilih sekaligus: <em>Laporan Penghasilan</em> + <em>Order Completed</em> (Shopee) + <em>Master Produk</em> (Jakmall).</p>
        </div>
        <button class="btn btn-primary full">Import Sekarang</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card pad">
    <h2 class="card-title">File yang dikenali otomatis</h2>
    <ul class="steps">
      <li><strong>Laporan Penghasilan Shopee</strong> (.xlsx) → angka penghasilan & biaya riil per pesanan (biaya admin, layanan, komisi) serta laba bersih.</li>
      <li><strong>Order Completed Shopee</strong> (.xlsx) → daftar item, <strong>SKU penjual</strong> &amp; jumlah per pesanan (dihubungkan via No. Pesanan).</li>
      <li><strong>Master Produk Jakmall</strong> (.xlsx) → modal/HPP per SKU; otomatis mengisi katalog <a class="link" href="<?= e(url('products')) ?>">Produk &amp; HPP</a>.</li>
      <li>CSV pesanan format lama tetap didukung.</li>
    </ul>
    <p class="hint">Agar HPP & laba lengkap: unggah <em>Laporan Penghasilan</em> + <em>Order Completed</em> untuk <strong>periode yang sama</strong>, dan pastikan <em>Master Produk Jakmall</em> sudah pernah diunggah (untuk modal per SKU).</p>
    <p class="hint">Import ulang file yang sama tidak menggandakan data — pesanan dengan nomor yang sudah ada akan dilewati.</p>
    <a class="btn btn-secondary full" href="templates/contoh-pesanan.csv" download>⬇️ Template CSV (opsional)</a>
  </div>
</div>
