# MarkazHub v2 — Rencana Arsitektur & Migrasi

> Tujuan: ubah MarkazHub jadi SaaS multi-seller, **cepat untuk data besar**, **aman**,
> **terstruktur**, **mudah dipakai**, siap untuk **app mobile (Android/iOS)** nanti.
> Logika laba yang sudah diaudit (benar) **wajib dipertahankan persis** lewat golden-test.

---

## 1. Prinsip dasar (tidak boleh dilanggar)
1. **API-first & berlapis** — logika bisnis di `app/Services`, bukan di UI. Web (Filament) & API (mobile) sama-sama tipis di atas Service yang sama → satu sumber kebenaran.
2. **Multi-tenant aman** — tiap seller = 1 tenant; data terisolasi otomatis (global scope), dites khusus agar tak bocor antar-seller.
3. **Performa sejak desain** — index tepat, hindari N+1, paginasi, cache, job latar belakang, tabel ringkasan untuk laporan.
4. **Validasi berlapis** — DB constraint + Form Request + validasi Filament → cegah data salah masuk.
5. **Selalu versi STABIL terbaru** (bukan beta) demi keandalan.

---

## 2. Stack teknologi (versi terbaru stabil — cek lagi saat kickoff)
| Lapisan | Pilihan | Catatan |
|---|---|---|
| Bahasa | **PHP 8.3** (atau 8.4 bila tersedia) | OPcache + JIT aktif |
| Framework | **Laravel** (mayor terbaru, mis. 12.x) | `composer create-project` ambil terbaru |
| UI Web | **Filament** (mayor terbaru stabil, 3.x/4.x) | panel admin seller |
| Halaman publik | **Blade** | landing/marketing |
| Auth API mobile | **Laravel Sanctum** | token |
| Login Google | **Laravel Socialite** + plugin Filament Socialite | "Masuk dengan Google" |
| Hak akses | **spatie/laravel-permission** | role: owner/staff/admin |
| Cache/Session/Queue | **Redis** (ideal) / fallback database | lihat §12 soal hosting |
| Database | **MySQL 8 / MariaDB 10.6+** | dari schema lama |
| Baca xlsx/csv | port **pembaca pure-PHP kita** jadi Service (atau maatwebsite/excel) | logika teruji dipertahankan |
| Aset | **Vite + Tailwind** (di-build di laptop, server tanpa Node) | tema biru |
| Test | **Pest/PHPUnit** | golden-test laba |

---

## 3. Arsitektur (satu otak, banyak kepala)
```
            app/Services  (LOGIKA: ProfitService, OrderImporter,
                           HppResolver, ReconcileService, DropshipService)
                 ▲                ▲                 ▲
          Filament (web)    API /api/v1        Jobs/Queue (import,
          Resources         (Controllers +      sync API marketplace)
                            Sanctum + Resources)
                                   ▲
                          App Android/iOS (Flutter/React Native) — nanti
```
- Filament Resource & API Controller **hanya memanggil Service** — tidak menaruh logika di dalamnya.
- `ProfitService` = rumus laba tunggal (hasil audit) → dipakai web, API, laporan.

---

## 4. Struktur folder
```
app/
  Models/            Eloquent (Organization, User, Store, Product, Order, OrderItem, ...)
  Services/          Logika domain (Profit, Import, Hpp, Reconcile, Dropship)
  Support/           Value object (Money), helper angka/tanggal ID
  Filament/Resources/  UI web (OrderResource, ProductResource, StoreResource, ...)
  Filament/Widgets/    Dashboard (stat & grafik)
  Http/
    Controllers/Api/V1/  endpoint mobile
    Requests/            Form Request (validasi)
    Resources/           API JSON resources
  Jobs/              ImportReportJob, SyncMarketplaceJob
  Policies/          otorisasi
database/migrations/ schema (dgn tenant_id + index)
tests/Feature|Unit/  termasuk golden-test laba
```

---

## 5. Model data & multi-tenancy
- Tambah tabel **`organizations`** (= seller/tenant) dan **`users`** (punya `organization_id`, role).
- **Semua tabel data** (`stores, products, product_marketplace_ids, orders, order_items, ...`) dapat kolom **`organization_id`**.
- **Global Scope** otomatis menyaring `organization_id` tenant aktif di SETIAP query (web & API) → mustahil lihat data seller lain.
- Filament pakai **fitur tenancy resmi** (tenant = Organization).
- Migrasi dari `schema.sql`: tabel sama + `organization_id` + index (lihat §6). Data v1 → seed ke 1 organization milik Anda.

---

## 6. Performa untuk DATA BESAR (prioritas)
1. **Index sesuai pola query** (kunci kecepatan):
   - `orders`: UNIQUE `(organization_id, external_no)`; index `(organization_id, order_date)`, `(organization_id, status)`, `(organization_id, marketplace, order_date)`.
   - `order_items`: index `(order_id)`, `(sku)`.
   - `product_marketplace_ids`: UNIQUE `(marketplace_product_id)`.
2. **Hindari N+1** — selalu `with()` (eager load) relasi yang dipakai di tabel Filament.
3. **Paginasi & cursor** — Filament paginasi bawaan; untuk daftar sangat besar pakai cursor pagination.
4. **Tabel RINGKASAN (pre-aggregate)** untuk dashboard/laporan: `daily_store_summaries` (omzet, laba, jumlah pesanan per hari/toko) di-refresh saat import. Dashboard baca ringkasan, **bukan** scan jutaan baris → instan.
5. **Cache** (Redis): cache statistik dashboard, daftar produk, dsb. (TTL pendek).
6. **Import berat → Queue Job** (latar belakang), proses **chunk** + transaksi, supaya web tetap responsif.
7. **OPcache + JIT** aktif; query log dimatikan di produksi.
8. **Kolom uang `DECIMAL`**, hitung di PHP/SQL dengan hati-hati (Value Object `Money`).
9. **Select kolom seperlunya** di tabel besar; defer kolom berat.

> Target: halaman daftar & dashboard tetap cepat walau ratusan ribu+ pesanan.

---

## 7. Keamanan (performa & keamanan = prioritas)
- **Isolasi tenant** (global scope) + **test khusus** "seller A tak bisa akses data seller B".
- **Otorisasi**: Policy per model + role (spatie). Filament hormati Policy.
- **Validasi input**: Form Request + aturan Filament (lihat §8).
- **Eloquent** (anti SQL-injection) + **mass-assignment** dijaga (`$fillable`).
- **CSRF** (web) bawaan; **Sanctum** + **ability/scope** + **rate limiting** untuk API.
- **HTTPS** wajib; **secrets di `.env`** (jangan commit); rotasi kredensial lama.
- **2FA** (opsional, didukung Filament); **audit log** aksi penting.
- **Login Google** lewat OAuth resmi (lihat §9) — kurangi risiko password.

---

## 8. Validasi (hindari segala kesalahan)
Tiga lapis agar data salah tak pernah masuk:
1. **Database**: `NOT NULL`, `UNIQUE`, foreign key, tipe `DECIMAL` — benteng terakhir.
2. **Aplikasi**: **Form Request** (web) & **rules** (API) — validasi terstruktur, pesan jelas.
3. **Filament form**: validasi real-time di UI + konfirmasi untuk aksi merusak (hapus/kosongkan).
4. **Import**: laporan status per-file (sudah ada di v1) dipertahankan; baris invalid dilewati + dilaporkan, bukan menggagalkan semua.

---

## 9. Login dengan Gmail (Google)
- **Laravel Socialite** (driver Google) + plugin **Filament Socialite** → tombol **"Masuk dengan Google"** di halaman login.
- Alur: Google OAuth → buat/cocokkan `users.email` → kaitkan ke `organization` → masuk.
- Tetap sediakan login email/password sebagai alternatif. Verifikasi email aktif.

---

## 10. UI/UX — biru, clean, responsif, mudah
- **Tema Filament** dengan **primary = biru** (palet Tailwind `blue`/`sky`), tampilan **clean** (banyak ruang putih, tipografi rapi).
- **Responsif** bawaan Filament (mobile/desktop) — tabel jadi kartu di layar kecil.
- **Mudah dipakai**: navigasi ringkas, label Bahasa Indonesia, badge status berwarna (laba final / belum), filter & pencarian cepat, empty-state yang membimbing.
- **Konsistensi** komponen (Filament) → seragam & profesional.

---

## 11. API untuk mobile (disiapkan dari awal)
- **`/api/v1/...`** ber-versi, auth **Sanctum** (token), output **API Resource** (kontrak JSON stabil).
- Endpoint awal: auth (login Google/token), dashboard ringkas, daftar/detail pesanan, produk, laporan laba per periode.
- **Rate limiting** + ability per token. Dokumentasi **OpenAPI** (opsional) untuk developer mobile.
- App mobile (nanti): **Flutter** (1 kode Android+iOS) atau React Native — hanya "konsumen" API; fondasi tak berubah.

---

## 12. Infrastruktur & hosting (jujur soal performa)
- **Bisa MULAI di Hostinger shared** (Business+: SSH, Composer, PHP 8.3, cron). Queue pakai driver **database + cron**, cache **database/file**.
- **TAPI** untuk target "data besar + sangat cepat + optimal + multi-seller produksi", sangat disarankan **VPS** (mis. Hostinger VPS/Cloud) agar bisa: **Redis** (cache/queue/session), **queue worker non-stop**, OPcache penuh, RAM/CPU memadai, webhook API real-time.
- **Rekomendasi praktis:** kembangkan & uji di shared hosting / lokal; **naik VPS saat rilis untuk seller lain.** Laravel pindah tanpa ubah kode.

### 12a. Upgrade PHP (Hostinger)
- **Cara paling andal:** hPanel → **PHP Configuration** → pilih **8.3**. Sekali klik, pasti berlaku.
- `.htaccess` (mis. `AddHandler application/x-httpd-php83 .php`) **kadang jalan, kadang diabaikan** di LiteSpeed/hPanel → jangan diandalkan; pakai hPanel sebagai patokan.

### 12b. Deploy TANPA SSH (full upload via FTP) — sesuai preferensi user
> Laravel BEDA dari v1: ia butuh folder **`vendor/`** (hasil Composer) & **migration DB**. Tak bisa "tulis lalu jalan" begitu saja. Tapi **bisa FTP-only** dengan pola **build di laptop, upload semua**:
1. Di **laptop** (Laragon sudah ada PHP+Composer+Node): `composer install --no-dev --optimize-autoloader` + `npm run build` → menghasilkan `vendor/` + aset `public/build` jadi.
2. **Migrasi DB dijalankan dari LAPTOP ke DB remote** (terbukti bisa: PDO ke `153.92.9.1`) — `php artisan migrate --force` dengan `.env` diarahkan ke DB hosting. **Tidak perlu SSH.**
3. **Upload SEMUA** lewat FTP (termasuk `vendor/`). Tips: zip → upload → **Extract via File Manager hPanel** (jauh lebih cepat & andal daripada FTP ribuan file `vendor/`).
4. Set **document root domain → folder `/public`** (hPanel) **atau** taruh isi `public/` di web root + sesuaikan `index.php` path.
5. `.env` di server (jangan di-commit) berisi APP_KEY, kredensial DB, Google OAuth.
- **Konsekuensi jujur:** tiap update yang mengubah dependency → harus build ulang + upload `vendor/` lagi (lambat via FTP murni; pakai zip+extract). Karena Anda **punya SSH**, memakainya **hanya untuk** `composer install` + `php artisan migrate` akan jauh lebih ringan — opsional, hak Anda. Skrip `deploy.sh` v1 bisa diadaptasi untuk pola zip-upload ini.

---

## 13. Roadmap migrasi (bertahap, aman)
**Fase 0 — Kunci kebenaran (sebelum coding v2)**
- Tulis **golden-test**: ambil hasil laba/net v1 yang sudah diaudit jadi fixture. Nanti `ProfitService` v2 harus hasilkan angka **identik** (selisih 0).

**Fase 1 — Fondasi Laravel**
- Project Laravel terbaru, PHP 8.3, Filament, Sanctum, Socialite, spatie/permission.
- Auth + login Google + multi-tenant (Organization) + role.
- Tema biru clean.

**Fase 2 — Data**
- Migrasi dari `schema.sql` → migration (+`organization_id` + index §6).
- Seed data v1 ke organization Anda (skrip migrasi data).

**Fase 3 — Logika (port, bukan tulis ulang)**
- Port ke `app/Services`: `ProfitService`, `OrderImporter` (xlsx/csv), `HppResolver` (+ histori harga & backfill), `ReconcileService`, `DropshipService`.
- **Lulus golden-test** → angka sama persis dengan v1.

**Fase 4 — UI web (Filament)**
- Resource: Pesanan, Produk, Toko, Supplier; halaman Import (laporan per-file), Dashboard (widget + ringkasan), Danger Zone.
- Tabel besar pakai index + eager load + ringkasan (§6).

**Fase 5 — API v1**
- Endpoint inti untuk mobile (auth, dashboard, pesanan, produk, laporan).

**Fase 6 — Produksi**
- Naik VPS (bila perlu), Redis, queue worker, monitoring, backup. Onboarding seller lain.

**Fase 7 — Mobile**
- App Flutter/React Native di atas API v1.

---

## 14. Risiko & mitigasi
| Risiko | Mitigasi |
|---|---|
| Angka laba berubah saat port | **Golden-test** wajib lulus (selisih 0) |
| Data tenant bocor | Global scope + **test isolasi** |
| Shared hosting lambat utk data besar | Tabel ringkasan + cache; naik **VPS** saat produksi |
| Import besar bikin web hang | **Queue job** + chunk |
| "Terbaru" = tak stabil | Pakai versi **stabil** terbaru, bukan beta |
| API marketplace gated/approval lama | Tetap pertahankan import file sebagai jembatan |

---

## 15. Ide tambahan (rekomendasi saya)
**Penting untuk SaaS (data seller = tanggung jawab besar):**
- **Backup otomatis DB** (`spatie/laravel-backup`) + retensi → jangan sampai data seller hilang.
- **Soft delete** (bukan hapus permanen) → pesanan/produk bisa dipulihkan; selaras kekhawatiran "jangan ada pesanan hilang".
- **Activity log** (`spatie/laravel-activitylog`) → siapa mengubah apa (penting saat multi-user).
- **Error monitoring** produksi (Sentry/Flare) → tahu bila ada error sebelum seller komplain.
- **Health check** & uptime monitor.

**Fitur bernilai bisnis (manfaatkan hasil audit kita):**
- **Dashboard "Produk Merugi"** → tandai pesanan/produk yang jual di bawah modal+fee (kita sudah temukan kasusnya) → seller bisa naikkan harga.
- **Insight**: produk terlaris, tren margin, perbandingan channel, rasio retur, biaya ongkir gratis.
- **Notifikasi** (Filament + email): import selesai, **reminder "dana belum cair / belum ada Laporan Penghasilan"**, alert pesanan rugi.
- **Export laporan** PDF/Excel per periode + opsi **email terjadwal** laporan bulanan.
- **Riwayat harga HPP** (sudah ada konsepnya) → grafik perubahan modal per produk.

**Pertumbuhan & monetisasi:**
- **Billing langganan** (nanti) pakai **Midtrans** (lokal Indonesia) + paket Free/Pro + **batas kuota** per paket (jumlah toko/pesanan).
- **Onboarding terpandu** seller baru (tambah toko → import file pertama).
- **2FA** + verifikasi email (keamanan akun seller).
- **Adapter marketplace yang mudah ditambah** (Lazada/Blibli menyusul) — pola adapter v1 dipertahankan.
- **PWA** (web bisa "di-install" di HP) → jembatan cepat sebelum app native Flutter jadi.
- **i18n** (struktur multi-bahasa) — sekarang Indonesia, mudah tambah nanti.

> **DISEPAKATI WAJIB (2026-06-22):** backup otomatis, soft delete, activity log, error monitoring, **dashboard "Produk Merugi"**, **notifikasi**, **insight** (produk terlaris/tren margin/rasio retur). Sisanya (billing, PWA, dll) bertahap.

### Langkah berikutnya yang saya sarankan
1. Anda **cek paket Hostinger** (SSH + Composer + PHP 8.3 + cron) → tentukan shared dulu / langsung VPS.
2. Saya mulai **Fase 0**: buat **golden-test** dari data v1 (kunci angka laba) — fondasi paling penting sebelum apa pun.
