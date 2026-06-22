<?php

namespace App\Services;

use App\Models\Category;

/**
 * Daftar kategori standar + tarif % biaya admin per channel, disusun mengikuti
 * struktur kategori & tarif RESMI marketplace (Shopee kluster Fashion/Elektronik/
 * FMCG/Lifestyle tier A–E; Tokopedia/TikTok komisi ~8% mayoritas, sama utk kedua
 * platform). Tarif = perkiraan resmi 2026, BISA diedit user di menu Kategori.
 *
 * Dipakai untuk seed SETIAP organisasi (saat signup & backfill) agar tiap seller
 * langsung punya kategori.
 */
class DefaultCategories
{
    /** [nama, fee_shopee, fee_tokotiktok] */
    public const LIST = [
        ['Pakaian Pria', 10.0, 8.0],
        ['Pakaian Wanita', 10.0, 8.0],
        ['Fashion Muslim', 10.0, 8.0],
        ['Sepatu', 10.0, 8.0],
        ['Tas & Dompet', 10.0, 8.0],
        ['Aksesoris Fashion', 10.0, 8.0],
        ['Jam Tangan & Kacamata', 9.0, 8.0],
        ['Kecantikan & Makeup', 9.5, 8.0],
        ['Perawatan Diri', 9.5, 8.0],
        ['Kesehatan & Suplemen', 6.75, 8.0],
        ['Ibu & Bayi', 6.5, 8.0],
        ['Makanan & Minuman', 10.0, 8.0],
        ['Perlengkapan Rumah', 10.0, 8.0],
        ['Dapur', 10.0, 8.0],
        ['Elektronik', 9.0, 8.0],
        ['Audio, Kamera & Gaming', 9.0, 8.0],
        ['Handphone & Tablet', 5.25, 8.0],
        ['Komputer & Laptop', 5.25, 8.0],
        ['Olahraga & Outdoor', 10.0, 8.0],
        ['Otomotif', 9.0, 7.5],
        ['Hobi & Koleksi', 10.0, 8.0],
        ['Buku & Alat Tulis', 10.0, 8.0],
        ['Mainan & Anak', 10.0, 8.0],
        ['Logam Mulia & Perhiasan', 4.25, 5.0],
        ['Voucher & Digital', 2.5, 2.5],
        ['Lainnya', 8.0, 8.0],
    ];

    /** Buat kategori default untuk org (idempoten — lewati yang sudah ada). */
    public function seedForOrg(int $orgId): int
    {
        $created = 0;
        foreach (self::LIST as [$name, $shopee, $toko]) {
            $exists = Category::withoutGlobalScopes()
                ->where('organization_id', $orgId)->where('name', $name)->exists();
            if (! $exists) {
                $c = new Category(['name' => $name, 'fee_shopee' => $shopee, 'fee_tokotiktok' => $toko]);
                $c->organization_id = $orgId;
                $c->saveQuietly();
                $created++;
            }
        }
        return $created;
    }
}
