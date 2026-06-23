<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ProfitService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class Insight extends Page
{
    protected string $view = 'filament.pages.insight';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Insight';

    protected static ?string $title = 'Insight & Produk Merugi';

    protected static ?int $navigationSort = 5;

    /** Detail produk yang sedang dibuka (klik baris). */
    public ?array $detail = null;

    public function getViewData(): array
    {
        $profit = ProfitService::sqlProfit();

        $pesananRugi = Order::query()
            ->where('status', 'COMPLETED')
            ->whereRaw("($profit) < -1")
            ->orderByRaw("($profit) asc")
            ->limit(25)->get();

        // Produk dijual DI BAWAH MODAL + total kerugiannya.
        $bawahModal = OrderItem::query()
            ->whereColumn('unit_price', '<', 'unit_cost')
            ->where('unit_cost', '>', 0)->where('unit_price', '>', 0)
            ->whereNotNull('sku')
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS qty_terjual, ROUND(AVG(unit_price)) AS avg_jual, ROUND(AVG(unit_cost)) AS avg_modal, ROUND(SUM((unit_cost - unit_price) * qty)) AS total_rugi')
            ->groupBy('sku')
            ->orderByDesc('total_rugi')
            ->limit(20)->get();

        // Produk PALING UNTUNG (margin × qty), pesanan selesai.
        $palingUntung = OrderItem::query()
            ->whereColumn('unit_price', '>', 'unit_cost')
            ->where('unit_cost', '>', 0)
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS qty_terjual, ROUND(SUM((unit_price - unit_cost) * qty)) AS total_untung')
            ->groupBy('sku')
            ->orderByDesc('total_untung')
            ->limit(10)->get();

        $terlaris = OrderItem::query()
            ->whereNotNull('sku')
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED'))
            ->selectRaw('sku, MAX(name) AS name, SUM(qty) AS total_qty')
            ->groupBy('sku')->orderByDesc('total_qty')
            ->limit(20)->get();

        // Statistik
        $selesai = Order::query()->where('status', 'COMPLETED');
        $totalLaba = (float) (clone $selesai)->sum(DB::raw($profit));
        $totalOmzet = (float) (clone $selesai)->sum(DB::raw('product_revenue + other_income'));
        $margin = $totalOmzet > 0 ? round($totalLaba / $totalOmzet * 100, 1) : 0;
        $jmlRugi = (int) (clone $selesai)->whereRaw("($profit) < 0")->count();
        $nilaiRugi = (float) (clone $selesai)->whereRaw("($profit) < 0")->sum(DB::raw($profit));
        $totalPesanan = Order::query()->count();
        $jmlRetur = Order::query()->where('status', 'RETURNED')->count();
        $jmlBatal = Order::query()->where('status', 'CANCELLED')->count();
        $rasioRetur = $totalPesanan > 0 ? round($jmlRetur / $totalPesanan * 100, 1) : 0;
        $jmlProdukRugi = $bawahModal->count();

        return compact(
            'pesananRugi', 'bawahModal', 'palingUntung', 'terlaris',
            'jmlRugi', 'nilaiRugi', 'jmlRetur', 'jmlBatal', 'rasioRetur', 'totalPesanan',
            'totalLaba', 'margin', 'jmlProdukRugi',
        );
    }

    /** Buka detail satu produk (dipanggil saat baris diklik). */
    public function showDetail(string $sku): void
    {
        $items = OrderItem::query()->where('sku', $sku)
            ->where('unit_cost', '>', 0)->where('unit_price', '>', 0)->get();
        if ($items->isEmpty()) {
            return;
        }
        $product = Product::query()->where('sku', $sku)->with('category')->first();
        $rugi = $items->filter(fn ($i) => (float) $i->unit_price < (float) $i->unit_cost);

        $this->detail = [
            'sku' => $sku,
            'name' => (string) $items->first()->name,
            'kategori' => $product?->category?->name,
            'hpp' => $product ? (float) $product->cost_price : null,
            'total_terjual' => (int) $items->sum('qty'),
            'transaksi_rugi' => $rugi->count(),
            'qty_rugi' => (int) $rugi->sum('qty'),
            'total_rugi' => (float) $rugi->sum(fn ($i) => ((float) $i->unit_cost - (float) $i->unit_price) * (int) $i->qty),
            'avg_jual' => round((float) $items->avg('unit_price')),
            'avg_modal' => round((float) $items->avg('unit_cost')),
        ];
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }
}
