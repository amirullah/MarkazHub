<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan Pesanan',
    'Unggah CSV laporan pesanan dari Shopee, Tokopedia, atau TikTok Shop. SKU dicocokkan ke katalog untuk menghitung HPP & laba.');
?>
<div class="two-col">
  <div class="card pad">
    <?php if (count($stores) === 0): ?>
      <p class="muted">Tambah minimal satu <a class="link" href="<?= e(url('stores')) ?>">toko</a> dulu sebelum import.</p>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_orders">
        <div class="field">
          <label class="label">Toko Tujuan</label>
          <select name="store_id" class="input" required>
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
          <label class="label">File CSV Laporan Pesanan</label>
          <input type="file" name="file" accept=".csv,text/csv" required class="input">
        </div>
        <button class="btn btn-primary full">Import Sekarang</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card pad">
    <h2 class="card-title">Cara pakai</h2>
    <ol class="steps">
      <li>Ekspor laporan pesanan dari Seller Center marketplace dalam format CSV.</li>
      <li>Pastikan produk sudah terdaftar di menu <a class="link" href="<?= e(url('products')) ?>">Produk &amp; HPP</a> agar modal terhitung otomatis lewat SKU.</li>
      <li>Pilih toko tujuan & jenis pemenuhan, lalu unggah file.</li>
    </ol>
    <p class="hint">Sistem mengenali nama kolom umum (nomor pesanan, nama produk, SKU, jumlah, harga, biaya admin, dll) dari berbagai format ekspor.</p>
    <a class="btn btn-secondary full" href="templates/contoh-pesanan.csv" download>⬇️ Unduh Template CSV Contoh</a>
    <p class="hint">Import ulang file yang sama tidak menggandakan data — pesanan dengan nomor yang sudah ada akan dilewati.</p>
  </div>
</div>
