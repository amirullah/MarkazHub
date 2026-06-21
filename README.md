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
1. Ekspor laporan pesanan (CSV) dari Seller Center marketplace.
2. Daftarkan produk + SKU + HPP di menu **Produk & HPP**.
3. Menu **Import Laporan** → pilih toko & jenis pemenuhan → unggah CSV.

Sistem mengenali nama kolom umum dari berbagai format ekspor. Template contoh ada di
`templates/contoh-pesanan.csv` (bisa diunduh dari halaman Import). Import ulang file
yang sama tidak menggandakan data.

## Struktur file
```
index.php            Front controller / router (index.php?p=halaman)
config.php           Kredensial database (jangan dibagikan)
schema.sql           Struktur tabel + data contoh (import via phpMyAdmin)
inc/db.php           Koneksi PDO + helper kueri
inc/helpers.php      Format uang, perhitungan laba, label
inc/marketplace.php  Parser CSV (toleran variasi kolom)
inc/actions.php      Proses form (buat/ubah/hapus/import)
inc/layout.php       Header/sidebar/footer
pages/               Halaman: dashboard, orders, order_detail, import, products, stores, suppliers
assets/style.css     Tampilan
templates/           Template CSV contoh
```
