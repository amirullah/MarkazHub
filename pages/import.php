<?php
$stores = q('SELECT id, name, marketplace FROM stores WHERE active = 1 ORDER BY name');
page_header('Import Laporan',
    'Unggah langsung file ekspor dari Shopee / Tokopedia / TikTok & Jakmall — tanpa template khusus. Sistem mengenali jenis tiap file otomatis.');

// Slot dikelompokkan per sumber agar jelas. Deteksi tetap berdasarkan ISI file,
// jadi slot hanya panduan; semua dikirim sebagai files[].
// Tiap grup punya 'ch' (channel) — slot disaring sesuai toko yang dipilih.
// Tiap slot: [judul, ekstensi, deskripsi, contoh nama file].
$groups = [
    ['ch' => 'SHOPEE', 'title' => '📦 File Shopee', 'note' => 'untuk toko Shopee', 'slots' => [
        ['Laporan Penghasilan Shopee', '.xlsx', 'Biaya admin/komisi/layanan + laba bersih per pesanan.', 'Income.sudah dilepas.id.20260101_20260621.xlsx'],
        ['Order Completed Shopee', '.xlsx', 'Produk, SKU penjual &amp; jumlah (qty).', 'Order.completed.20260401_20260501.xlsx'],
    ]],
    ['ch' => 'TOKOTIKTOK', 'title' => '🛒 File Tokopedia / TikTok', 'note' => 'untuk toko Tokopedia/TikTok', 'slots' => [
        ['Laporan Penghasilan Tokopedia/TikTok', '.xlsx', 'Total Pendapatan, Total Biaya &amp; uang bersih per pesanan.', 'income_20260621195457(UTC+7).xlsx'],
        ['Pesanan Selesai Tokopedia/TikTok', '.csv', 'Produk, Seller SKU &amp; jumlah (qty).', 'Selesai pesanan-2026-06-21-18_40.csv'],
    ]],
    ['ch' => 'ALL', 'title' => '🚚 File Jakmall', 'note' => 'untuk semua toko', 'slots' => [
        ['Master Produk Jakmall', '.xlsx', 'Modal/HPP per SKU + ID produk; isi katalog otomatis.', '20260621_MasterProduct.xlsx'],
        ['Laporan Pesanan Jakmall', '.xlsx', 'Deteksi pesanan dropship + biaya mitra Jakmall.', 'LaporanPesanan-23032026-20062026.xlsx'],
    ]],
];
$importReport = $_SESSION['import_report'] ?? null;
unset($_SESSION['import_report']);
?>
<?php if ($importReport): ?>
  <?php $okN = count(array_filter($importReport, fn($r) => $r['ok'])); $failN = count($importReport) - $okN; ?>
  <div class="card pad import-report">
    <div class="report-head">📋 Hasil import per file — <span class="rep-ok-txt"><?= $okN ?> berhasil</span><?php if ($failN): ?>, <span class="rep-fail-txt"><?= $failN ?> gagal</span><?php endif; ?></div>
    <table class="report-table">
      <tbody>
      <?php foreach ($importReport as $r): ?>
        <tr class="<?= $r['ok'] ? 'rep-ok' : 'rep-fail' ?>">
          <td class="rep-icon"><?= $r['ok'] ? '✅' : '❌' ?></td>
          <td class="rep-name"><?= e($r['name']) ?></td>
          <td class="rep-info">
            <?php if ($r['ok']): ?>
              <span class="rep-type"><?= e($r['type']) ?></span> · <span class="muted"><?= e($r['detail'] ?? '') ?></span>
            <?php else: ?>
              <span class="rep-reason"><?= e($r['reason']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php $hppChanges = $_SESSION['hpp_changes'] ?? null; unset($_SESSION['hpp_changes']); ?>
<?php if ($hppChanges): ?>
  <div class="card pad import-report">
    <div class="report-head">💲 Perubahan harga HPP terdeteksi: <span class="rep-fail-txt"><?= count($hppChanges) ?> SKU</span></div>
    <table class="report-table">
      <thead><tr><th>SKU</th><th>Produk</th><th class="right">HPP lama</th><th class="right">HPP baru</th><th class="right">Selisih</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($hppChanges, 0, 100) as $c): $d = $c['new'] - $c['old']; ?>
        <tr>
          <td class="rep-name"><?= e($c['sku']) ?></td>
          <td><?= e(mb_strimwidth((string) $c['name'], 0, 42, '…')) ?></td>
          <td class="right"><?= rupiah($c['old']) ?></td>
          <td class="right"><?= rupiah($c['new']) ?></td>
          <td class="right <?= $d > 0 ? 'neg' : 'pos' ?>"><?= ($d > 0 ? '+' : '') . rupiah($d) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($hppChanges) > 100): ?><p class="hint">…dan <?= count($hppChanges) - 100 ?> SKU lain.</p><?php endif; ?>
    <p class="hint">Pesanan lama <strong>tidak berubah</strong> (tetap pakai HPP saat itu). Untuk menerapkan harga baru ke pesanan lama, centang <em>"Perbarui HPP pesanan lama"</em> saat import Master.</p>
  </div>
<?php endif; ?>
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
              <option value="<?= $s['id'] ?>" data-ch="<?= e(CHANNEL_OF[$s['marketplace']]) ?>"><?= e($s['name']) ?> · <?= e(CHANNEL_LABEL[CHANNEL_OF[$s['marketplace']]]) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Slot file di bawah otomatis menyesuaikan channel toko ini, jadi tak akan keliru marketplace.</p>
        </div>

        <div class="step-head"><span class="num">2</span>Pilih file (klik tiap kotak — boleh beberapa file)</div>
        <p class="hint pick-store-hint">↑ Pilih toko dulu untuk menampilkan slot file pesanannya.</p>
        <?php foreach ($groups as $g): ?>
          <div class="upload-group" data-ch="<?= e($g['ch']) ?>">
            <div class="upload-group-head"><?= $g['title'] ?> <span class="muted">· <?= e($g['note']) ?></span></div>
            <div class="upload-slots">
              <?php foreach ($g['slots'] as $sl): ?>
                <label class="upload-slot">
                  <div class="upload-body">
                    <div class="upload-title"><?= e($sl[0]) ?> <span class="muted">(<?= e($sl[1]) ?>)</span></div>
                    <div class="upload-desc"><?= $sl[2] ?></div>
                    <div class="upload-eg muted tiny">contoh nama file: <code><?= e($sl[3]) ?></code></div>
                    <div class="file-status muted" data-status>Belum dipilih</div>
                  </div>
                  <input type="file" name="files[]" accept=".xlsx,.csv" class="js-file" multiple hidden>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <p class="hint" style="margin-top:10px">Pemenuhan terdeteksi otomatis: pesanan yang ada di <strong>Laporan Pesanan Jakmall</strong> = <strong>Dropship</strong> (modal = total transaksi Jakmall termasuk biaya mitra); sisanya = <strong>Packing Sendiri</strong>. Tak perlu pilih manual.</p>

        <div class="upload-group" data-ch="ALL" style="margin-top:6px">
          <div class="upload-group-head">⚙️ Opsi HPP (saat unggah Master Produk Jakmall)</div>
          <label class="radio-line"><input type="checkbox" name="update_old_hpp" value="1" id="upd-hpp"> Perbarui HPP <strong>pesanan lama</strong> dengan harga baru dari Master ini</label>
          <p class="hint">Default (tidak dicentang): pesanan lama tetap pakai HPP saat itu — <strong>histori harga terjaga</strong>. Centang hanya bila ingin menimpa HPP pesanan lama dengan harga baru.</p>
          <div class="field" id="upd-hpp-date" style="display:none; margin-top:6px">
            <label class="label">Hanya pesanan sejak tanggal (opsional)</label>
            <input type="date" name="update_old_hpp_since" class="input">
            <p class="hint">Kosongkan = semua periode. Isi mis. tanggal Master ini mulai berlaku, agar pesanan sebelum tanggal itu tetap pakai harga lama.</p>
          </div>
        </div>

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
        // Tampilkan slot file sesuai channel toko yang dipilih.
        (function () {
          var sel = document.querySelector('select[name=store_id]');
          var hint = document.querySelector('.pick-store-hint');
          function sync() {
            var opt = sel.options[sel.selectedIndex];
            var ch = opt ? (opt.getAttribute('data-ch') || '') : '';
            document.querySelectorAll('.upload-group').forEach(function (g) {
              var gch = g.getAttribute('data-ch');
              g.style.display = (gch === 'ALL' || (ch && gch === ch)) ? '' : 'none';
            });
            if (hint) hint.style.display = ch ? 'none' : '';
          }
          sel.addEventListener('change', sync);
          sync();
        })();
        // Tampilkan field tanggal saat opsi "perbarui HPP pesanan lama" dicentang.
        (function () {
          var cb = document.getElementById('upd-hpp'), d = document.getElementById('upd-hpp-date');
          if (cb && d) { cb.addEventListener('change', function () { d.style.display = cb.checked ? '' : 'none'; }); }
        })();
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
