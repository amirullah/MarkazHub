<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan',
    'Unggah langsung file ekspor dari Shopee / Tokopedia / TikTok & Jakmall — tanpa template khusus. Sistem mengenali jenis tiap file otomatis.');

// Slot dikelompokkan per sumber agar jelas. Deteksi tetap berdasarkan ISI file,
// jadi slot hanya panduan; semua dikirim sebagai files[].
$groups = [
    ['title' => '📦 File Shopee', 'note' => 'untuk toko Shopee', 'slots' => [
        ['Laporan Penghasilan Shopee', '.xlsx', 'Biaya admin/komisi/layanan riil + laba bersih per pesanan.'],
        ['Order Completed Shopee', '.xlsx', 'Daftar item: SKU penjual &amp; jumlah (dihubungkan via No. Pesanan).'],
    ]],
    ['title' => '🛒 File Tokopedia / TikTok', 'note' => 'untuk toko Tokopedia/TikTok', 'slots' => [
        ['Laporan Penghasilan Tokopedia/TikTok', '.xlsx', 'Settlement: Total Pendapatan, Total Biaya &amp; uang bersih per pesanan.'],
        ['Pesanan Selesai Tokopedia/TikTok', '.csv', 'Daftar item: Seller SKU &amp; jumlah per pesanan.'],
    ]],
    ['title' => '🚚 File Jakmall', 'note' => 'untuk semua toko', 'slots' => [
        ['Master Produk Jakmall', '.xlsx', 'Modal/HPP per SKU + ID produk marketplace; isi katalog otomatis.'],
        ['Laporan Pesanan Jakmall', '.xlsx', 'Deteksi pesanan dropship + biaya mitra Jakmall.'],
    ]],
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
          <p class="hint">Pilih toko sesuai marketplace file pesanan yang Anda unggah.</p>
        </div>

        <?php foreach ($groups as $g): ?>
          <div class="upload-group">
            <div class="upload-group-head"><?= $g['title'] ?> <span class="muted">· <?= e($g['note']) ?></span></div>
            <div class="upload-slots">
              <?php foreach ($g['slots'] as $sl): ?>
                <label class="upload-slot">
                  <div class="upload-body">
                    <div class="upload-title"><?= e($sl[0]) ?> <span class="muted">(<?= e($sl[1]) ?>)</span></div>
                    <div class="upload-desc"><?= $sl[2] ?></div>
                    <div class="file-status muted" data-status>Belum dipilih</div>
                  </div>
                  <input type="file" name="files[]" accept=".xlsx,.csv" class="js-file" hidden>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

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
              st.classList.remove('muted'); st.classList.add('file-ok');
              slot.classList.add('is-selected');
            } else {
              st.textContent = 'Belum dipilih';
              st.classList.add('muted'); st.classList.remove('file-ok');
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
      <li>Sekali / saat harga berubah: unggah <strong>Master Produk Jakmall</strong> (modal per SKU).</li>
      <li>Pilih <strong>toko tujuan</strong>, lalu unggah file <strong>Laporan Penghasilan</strong> + <strong>file Pesanan</strong> (Order Completed / Pesanan Selesai) untuk <strong>periode yang sama</strong>.</li>
      <li>Sertakan <strong>Laporan Pesanan Jakmall</strong> agar pesanan dropship + biaya mitranya tertandai otomatis.</li>
    </ol>
    <p class="hint">Boleh unggah beberapa file sekaligus. Setelah Import, pesan hasil merinci file apa saja yang terbaca &amp; jumlahnya. Import ulang tidak menggandakan data — pesanan lama justru dilengkapi.</p>
    <a class="btn btn-secondary full" href="templates/contoh-pesanan.csv" download>⬇️ Template CSV (opsional)</a>
  </div>
</div>
