<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Organization;

/**
 * Estimasi biaya marketplace untuk pesanan yang BELUM punya Laporan Penghasilan.
 * Meniru struktur biaya nyata 2026 yang punya BEBERAPA komponen:
 *
 *   Shopee          = Biaya Administrasi (komisi % kategori)
 *                   + Biaya Layanan (% dari subtotal, ada batas)
 *                   + Biaya Proses Pesanan (Rp1.250)
 *   Tokopedia/TikTok= Komisi platform (komisi % kategori)
 *                   + Komisi Dinamis (% dari subtotal)
 *                   + Biaya Proses Pesanan (Rp1.250)
 *
 * Aturan kelengkapan:
 *  - Pesanan tanpa omzet (mis. dibatalkan, product_revenue = 0) → biaya 0 (tak ada transaksi).
 *  - Produk tanpa kategori / SKU tak dikenal → pakai tarif komisi DEFAULT (rata-rata tarif
 *    kategori org) agar pesanan tetap diestimasi (tidak ada pesanan berjalan yang terlewat).
 *  - Saat Laporan Penghasilan resmi masuk, biaya admin asli menggantikan estimasi (income_verified).
 */
class AdminFeeEstimator
{
    /** Biaya proses pesanan per pesanan (Rp). Resmi 2026: Shopee & Tokopedia/TikTok = Rp1.250. */
    public const ORDER_PROCESSING_FEE = 1250.0;

    /** Tarif komisi cadangan bila org belum punya kategori sama sekali (%). */
    public const DEFAULT_COMMISSION_PCT = 8.0;

    /** Estimasi biaya marketplace satu pesanan (Rp). $fees = setelan biaya org (opsional, untuk batch). */
    public function estimate(Order $order, ?array $fees = null): float
    {
        // Tanpa omzet (dibatalkan / belum ada nilai) → tidak ada biaya.
        $revenue = (float) $order->product_revenue;
        if ($revenue <= 0) {
            return 0.0;
        }

        $order->loadMissing('items.product.category');
        $fees ??= $this->feesForOrg((int) $order->organization_id);
        $isShopee = $order->marketplace === 'SHOPEE';
        $defaultRate = $isShopee ? $fees['default_shopee_pct'] : $fees['default_tokotiktok_pct'];

        // Tarif komisi rata-rata tertimbang dari item (kategori → tarif kategori; tak dikenal → tarif default).
        $base = 0.0;
        $weighted = 0.0;
        foreach ($order->items as $item) {
            $itemRev = (float) $item->qty * (float) $item->unit_price;
            if ($itemRev <= 0) {
                continue;
            }
            $category = $item->product?->category;
            $rate = $category ? $category->feeForMarketplace($order->marketplace) : $defaultRate;
            $base += $itemRev;
            $weighted += $itemRev * $rate;
        }
        $commissionRate = $base > 0 ? $weighted / $base : $defaultRate;
        $commission = $revenue * $commissionRate / 100;

        // Komponen % kedua: Biaya Layanan (Shopee, ada batas) atau Komisi Dinamis (Tokped/TikTok).
        if ($isShopee) {
            $extra = $revenue * $fees['shopee_service_pct'] / 100;
            if ($fees['shopee_service_cap'] > 0) {
                $extra = min($extra, $fees['shopee_service_cap']);
            }
        } else {
            $extra = $revenue * $fees['tokotiktok_dynamic_pct'] / 100;
        }

        return round($commission + $extra + self::ORDER_PROCESSING_FEE, 2);
    }

    /** Setelan biaya tambahan + tarif default per-organisasi (default aman bila kolom/baris belum ada). */
    public function feesForOrg(int $orgId): array
    {
        $org = Organization::find($orgId);
        $avgShopee = (float) Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_shopee');
        $avgToko = (float) Category::withoutGlobalScopes()->where('organization_id', $orgId)->avg('fee_tokotiktok');

        return [
            'shopee_service_pct' => (float) ($org->fee_shopee_service_pct ?? 10),
            'shopee_service_cap' => (float) ($org->fee_shopee_service_cap ?? 10000),
            'tokotiktok_dynamic_pct' => (float) ($org->fee_tokotiktok_dynamic_pct ?? 6.5),
            'default_shopee_pct' => $avgShopee > 0 ? $avgShopee : self::DEFAULT_COMMISSION_PCT,
            'default_tokotiktok_pct' => $avgToko > 0 ? $avgToko : self::DEFAULT_COMMISSION_PCT,
        ];
    }

    /** Pesanan layak diestimasi: belum punya Laporan Penghasilan resmi. */
    public function isEligible(Order $order): bool
    {
        return ! $order->income_verified;
    }

    /**
     * Hitung ulang estimasi untuk SEMUA pesanan org yang BELUM final (overwrite estimasi lama
     * agar formula terbaru diterapkan; pesanan batal otomatis jadi 0). Pesanan dengan Laporan
     * Penghasilan (income_verified) TIDAK disentuh. saveQuietly agar tak membanjiri log.
     */
    public function applyToOrg(int $orgId): array
    {
        $fees = $this->feesForOrg($orgId);

        $orders = Order::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('income_verified', false)
            ->with('items.product.category')
            ->get();

        $updated = 0;
        $total = 0.0;

        foreach ($orders as $order) {
            $estimate = $this->estimate($order, $fees);
            if ((float) $order->admin_fee !== $estimate) {
                $order->admin_fee = $estimate;
                $order->saveQuietly();
            }
            if ($estimate > 0) {
                $updated++;
                $total += $estimate;
            }
        }

        return ['updated' => $updated, 'total' => round($total, 2), 'eligible' => $orders->count()];
    }
}
