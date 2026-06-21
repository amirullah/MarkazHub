# MarkazHub — versi PHP + MySQL 🛍️

Versi ini dirancang untuk **shared hosting biasa** (PHP + MySQL, upload via FTP).
Tidak butuh Node.js. Fitur sama dengan versi Next.js: kelola toko multi-marketplace
(Shopee/Tokopedia/TikTok), produk + HPP, supplier (termasuk Jakmall untuk dropship),
import laporan pesanan via CSV, dan **perhitungan keuntungan per pesanan**.

## Kebutuhan
- PHP 7.4+ (dengan PDO MySQL — standar di hampir semua hosting)
- MySQL / MariaDB

## Cara pasang di hosting (markazhub.mkz.my.id)

### 1. Buat / siapkan database
Di hPanel hosting, pastikan database & user MySQL sudah ada (Anda sudah punya:
`u1659760_markazhub`).

### 2. Import struktur + data contoh
Buka **phpMyAdmin** → pilih database `u1659760_markazhub` → tab **Import** →
pilih file `schema.sql` → **Go**. Ini membuat semua tabel dan mengisi data contoh.

### 3. Atur koneksi
Buka file `config.php`, pastikan isinya sesuai database Anda:
```php
'db_host' => 'localhost',          // umumnya 'localhost' di shared hosting
'db_name' => 'u1659760_markazhub',
'db_user' => 'u1659760_markazhub',
'db_pass' => 'PASSWORD_ANDA',
```
> Jika `localhost` gagal, ganti `db_host` ke IP yang diberi hosting (mis. `153.92.9.1`).

### 4. Upload file via FTP
Upload **seluruh isi folder ini** ke folder web hosting Anda
(`public_html/` atau folder domain `markazhub.mkz.my.id`) menggunakan FileZilla.
Struktur file harus tetap sama (folder `inc/`, `pages/`, `assets/`, `templates/`).

### 5. Selesai
Buka `https://markazhub.mkz.my.id` di browser. 🎉

## Keamanan
- Ganti password database secara berkala (apalagi bila pernah dibagikan).
- File `config.php`, `schema.sql`, dan `*.md` sudah diblokir dari akses web lewat `.htaccess`.

## Cara hitung laba
```
Pendapatan = harga produk + pendapatan lain
             (ongkir dibayar pembeli = pass-through, tidak masuk laba)
Total Biaya = HPP/modal + biaya admin + ongkir penjual
            + voucher penjual + biaya dropship (Jakmall) + biaya lain
Laba = Pendapatan − Total Biaya
```
Untuk pesanan **dropship**, modal dihitung dari biaya beli ke Jakmall, bukan HPP stok sendiri.

## Import laporan pesanan
Unggah langsung file ekspor (.xlsx/.csv) — tanpa template khusus. Di menu
**Import Laporan**: pilih toko tujuan, lalu unggah file sesuai marketplace:
- **Shopee:** Laporan Penghasilan + Order Completed
- **Tokopedia/TikTok:** Laporan Penghasilan + Pesanan Selesai (CSV)
- **Jakmall:** Master Produk (modal/HPP per SKU) + Laporan Pesanan (deteksi dropship)

Sistem mengenali jenis & kolom tiap file otomatis dari isinya, menggabungkannya per
nomor pesanan, dan menghitung laba dari uang bersih marketplace (Total Penghasilan /
penyelesaian) dikurangi modal. Import ulang tidak menggandakan data — pesanan lama
justru dilengkapi (SKU/HPP/biaya terisi saat file pelengkap menyusul).

## Struktur file
```
index.php            Front controller / router (index.php?p=halaman)
config.php           Kredensial database (jangan dibagikan)
schema.sql           Struktur tabel + data contoh (import via phpMyAdmin)
inc/db.php           Koneksi PDO + helper kueri
inc/helpers.php      Format uang, perhitungan laba, label
inc/marketplace.php  Parser ekspor (xlsx/csv) + deteksi format otomatis
inc/xlsx.php         Pembaca .xlsx murni-PHP (tanpa library)
inc/actions.php      Proses form (buat/ubah/hapus/import)
inc/layout.php       Header/sidebar/footer
pages/               Halaman: dashboard, orders, order_detail, import, products, stores, suppliers
assets/style.css     Tampilan
```
