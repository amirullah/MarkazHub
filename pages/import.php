<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan',
    'Unggah langsung file ekspor dari Shopee & Jakmall (.xlsx) — tanpa template khusus. Tiap jenis file punya slot sendiri; sistem mengenali isinya otomatis.');

// Definisi slot upload (label memandu; deteksi tetap berdasarkan isi file).
$slots = [
    ['key' => 'income', 'icon' => '📊', 'title' => 'Laporan Penghasilan Shopee',
        'desc' => 'Biaya admin/komisi/layanan riil + laba bersih per pesanan.'],
    ['key' => 'order', 'icon' => '📦', 'title' => 'Order Completed Shopee',
        'desc' => 'Daftar item, SKU penjual & jumlah (dihubungkan via No. Pesanan).'],
    ['key' => 'jakorder', 'icon' => '🚚', 'title' => 'Laporan Pesanan Jakmall',
        'desc' => 'Deteksi pesanan dropship + biaya mitra Jakmall. Tanpa file ini, pemenuhan pakai pilihan di bawah.'],
    ['key' => 'master', 'icon' => '🏷️', 'title' => 'Master Produk Jakmall',
        'desc' => 'Modal/HPP per SKU; mengisi katalog Produk & HPP otomatis.'],
];
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

        <label class="label">File untuk diunggah</label>
        <div class="upload-slots">
          <?php foreach ($slots as $sl): ?>
            <label class="upload-slot" for="f_<?= $sl['key'] ?>">
              <div class="upload-ic"><?= $sl['icon'] ?></div>
              <div class="upload-body">
                <div class="upload-title"><?= e($sl['title']) ?> <span class="muted">(.xlsx)</span></div>
                <div class="upload-desc"><?= e($sl['desc']) ?></div>
                <div class="file-status muted" data-status>Belum dipilih</div>
              </div>
              <input type="file" id="f_<?= $sl['key'] ?>" name="files[]" accept=".xlsx,.csv"
                     class="js-file" data-label="<?= e($sl['title']) ?>" hidden>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="field" style="margin-top:14px">
          <label class="label">Jika Laporan Pesanan Jakmall TIDAK diunggah, anggap pemenuhan sebagai:</label>
          <select name="fulfillment" class="input">
            <?php foreach (FULFILLMENTS as $f): ?>
              <option value="<?= $f ?>"><?= e(FULFILLMENT_LABEL[$f]) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Bila laporan Jakmall diunggah: pesanan yang ada di laporan = <strong>Dropship</strong> (modal = total transaksi Jakmall termasuk biaya mitra), sisanya = <strong>Packing Sendiri</strong>.</p>
        </div>

        <button class="btn btn-primary full">Import Sekarang</button>
      </form>
      <script>
        document.querySelectorAll('.js-file').forEach(function (inp) {
          inp.addEventListener('change', function () {
            var slot = inp.closest('.upload-slot');
            var st = slot.querySelector('[data-status]');
            if (inp.files && inp.files.length) {
              st.textContent = '✓ ' + inp.files[0].name;
              st.classList.remove('muted');
              st.classList.add('file-ok');
              slot.classList.add('is-selected');
            } else {
              st.textContent = 'Belum dipilih';
              st.classList.add('muted');
              st.classList.remove('file-ok');
              slot.classList.remove('is-selected');
            }
          });
        });
      </script>
    <?php endif; ?>
  </div>

  <div class="card pad">
    <h2 class="card-title">Cara pakai</h2>
    <ol class="steps">
      <li>Pertama kali / saat harga berubah: unggah <strong>Master Produk Jakmall</strong> untuk modal per SKU.</li>
      <li>Tiap periode: unggah <strong>Laporan Penghasilan</strong> + <strong>Order Completed</strong> Shopee untuk <strong>periode yang sama</strong>.</li>
      <li>Sertakan <strong>Laporan Pesanan Jakmall</strong> agar sistem otomatis menandai pesanan <em>dropship</em> beserta biaya mitranya.</li>
    </ol>
    <p class="hint">Bisa unggah beberapa slot sekaligus dalam satu kali Import. Setelah Import, pesan hasil akan merinci file apa saja yang terbaca & jumlahnya.</p>
    <p class="hint">Import ulang file yang sama tidak menggandakan data — pesanan dengan nomor yang sudah ada akan dilewati.</p>
    <a class="btn btn-secondary full" href="templates/contoh-pesanan.csv" download>⬇️ Template CSV (opsional)</a>
  </div>
</div>
