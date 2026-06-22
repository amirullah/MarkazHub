# Golden Test — pengunci angka laba (Fase 0 migrasi v2)

`scenarios.json` berisi skenario perhitungan laba yang **sudah diaudit benar** di v1
(mencerminkan kasus nyata yang diverifikasi ke file marketplace). Ini **kontrak**:
saat logika di-port ke Laravel, `ProfitService` v2 **wajib menghasilkan angka identik**
untuk input yang sama (selisih 0).

## Jalankan (v1, sekarang)
```
php tests/golden_test.php
```
Lulus = formula laba v1 terkunci.

## Dipakai di v2 (Laravel)
Skenario `scenarios.json` dipakai ulang di test Pest/PHPUnit:
- baca `scenarios.json`
- panggil `app(ProfitService::class)->profit($input)`
- assert sama dengan `expected_profit` (dan `expected_net` bila ada).

Jika v2 menghasilkan angka berbeda walau 1 rupiah → **bug porting**, perbaiki sebelum rilis.

## Aturan
- **Jangan** menaruh data bisnis seller nyata di file yang di-commit (privasi).
  Skenario di sini **sintetis tapi mewakili pola nyata**.
- Snapshot data nyata (untuk validasi penuh lokal) → simpan lokal & gitignore
  (pola `*_snapshot.json`), jangan commit/deploy.

## Aturan laba yang dikunci
- `profit = (product_revenue + other_income) − (cogs + admin_fee + shipping_cost_seller + voucher_seller_borne + dropship_cost + other_cost)`
- **Dropship**: `cogs = 0`, modal di `dropship_cost` (Total Transaksi Jakmall).
- **Retur**: `cogs = 0` (barang kembali); laba = net (0 utk refund penuh, minus bila ongkir tak kembali).
- **Batal**: semua finansial 0 → laba 0.
- **Income-only (belum ada file Pesanan)**: `cogs = 0` → laba overstate (belum final).
