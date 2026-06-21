<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan',
    'Unggah langsung file ekspor dari Shopee / Tokopedia / TikTok & Jakmall — tanpa template khusus. Sistem mengenali jenis tiap file otomatis.');

// Slot dikelompokkan per sumber agar jelas. Deteksi tetap berdasarkan ISI file,
// jadi slot hanya panduan; semua dikirim sebagai files[].
$groups = [
    ['title' => '📦 File Shopee', 'note' => 'untuk toko Shopee', 'slots' => [
        ['Laporan Penghasilan Shopee', '.xlsx', 'Biaya admin/komisi/layanan riil + laba bersih per pesanan. Boleh beberapa file (mis. per bulan) sekaligus.'],
        ['Order Completed Shopee', '.xlsx', 'Daftar item: SKU penjual &amp; jumlah (qty). <strong>Boleh pilih beberapa file bulanan sekaligus</strong> agar menutup periode Laporan Penghasilan.'],
    ]],
    ['title' => '🛒 File Tokopedia / TikTok', 'note' => 'untuk toko Tokopedia/TikTok', 'slots' => [
        ['Laporan Penghasilan Tokopedia/TikTok', '.xlsx', 'Settlement: Total Pendapatan, Total Biaya &amp; uang bersih per pesanan. Boleh beberapa file.'],
        ['Pesanan Selesai Tokopedia/TikTok', '.csv', 'Daftar item: Seller SKU &amp; jumlah (qty). Boleh pilih beberapa file bulanan sekaligus.'],
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
        <div class="step-head"><span class="num">1</span>Pilih toko tujuan</div>
        <div class="field">
          <select name="store_id" class="input">
            <option value="">— Pilih toko —</option>
            <?php foreach ($stores as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> · <?= e(CHANNEL_LABEL[CHANNEL_OF[$s['marketplace']]]) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Pilih toko sesuai marketplace file pesanan. File Shopee ke toko Shopee, file Tokopedia/TikTok ke toko Tokopedia/TikTok (kalau salah, otomatis ditolak).</p>
        </div>

        <div class="step-head"><span class="num">2</span>Pilih file (klik tiap kotak — boleh beberapa file)</div>
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
                  <input type="file" name="files[]" accept=".xlsx,.csv" class="js-file" multiple hidden>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <p class="hint" style="margin-top:10px">Pemenuhan terdeteksi otomatis: pesanan yang ada di <strong>Laporan Pesanan Jakmall</strong> = <strong>Dropship</strong> (modal = total transaksi Jakmall termasuk biaya mitra); sisanya = <strong>Packing Sendiri</strong>. Tak perlu pilih manual.</p>

        <button class="btn btn-primary full" style="margin-top:10px">Import Sekarang</button>
      </form>
      <script>
        document.querySelectorAll('.js-file').forEach(function (inp) {
          inp.addEventListener('change', function () {
            var slot = inp.closest('.upload-slot');
            var st = slot.querySelector('[data-status]');
            if (inp.files && inp.files.length) {
              st.textContent = inp.files.length === 1
                ? '✓ ' + inp.files[0].name
                : '✓ ' + inp.files.length + ' file dipilih';
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
    <p class="hint"><strong>Order Completed hanya bisa diunduh per bulan?</strong> Tidak masalah — unduh tiap bulan (mis. Des–Jun), lalu pilih <strong>semua file bulanan itu sekaligus</strong> di slot Order Completed (klik file sambil tahan Ctrl/⌘). Bisa juga impor sebagian dulu, lalu sisanya menyusul — pesanan lama otomatis dilengkapi, tidak menggandakan.</p>
  </div>
</div>

<div class="card pad danger-zone" style="margin-top:1.5rem">
  <h2 class="card-title" style="color:var(--neg)">⚠️ Kosongkan Data</h2>
  <p class="hint" style="margin-bottom:.75rem">Menghapus data permanen — tidak bisa dibatalkan. Untuk memulai ulang/membersihkan hasil import yang keliru.</p>
  <form method="post" onsubmit="return confirm('Yakin mengosongkan data? Tindakan ini PERMANEN dan tidak bisa dibatalkan.')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear_data">
    <div class="field">
      <label class="label">Cakupan</label>
      <label class="radio-line"><input type="radio" name="scope" value="orders" checked> Pesanan saja <span class="muted">(katalog produk tetap)</span></label>
      <label class="radio-line"><input type="radio" name="scope" value="all"> Semua <span class="muted">(pesanan + katalog produk + peta SKU)</span></label>
    </div>
    <div class="field">
      <label class="label">Ketik <strong>KOSONGKAN</strong> untuk konfirmasi</label>
      <input type="text" name="confirm" class="input" placeholder="KOSONGKAN" autocomplete="off" required style="max-width:240px">
    </div>
    <button class="btn btn-danger">Kosongkan Data Sekarang</button>
  </form>
</div>
