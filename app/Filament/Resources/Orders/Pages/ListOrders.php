<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\AdminFeeEstimator;
use App\Services\ProfitService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Kartu total di ATAS tabel — mengikuti filter (dihitung dari query terfilter). */
    public function getSubheading(): string|Htmlable|null
    {
        $query = $this->getFilteredTableQuery();
        if (! $query) {
            return null;
        }

        $count = (clone $query)->count();
        $omzet = (float) (clone $query)->sum('product_revenue');
        $laba = (float) (clone $query)->sum(DB::raw(ProfitService::SQL_PROFIT));
        $rp = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $card = fn (string $label, string $value, string $color, string $icon): string =>
            "<div style='flex:1 1 160px;border:1px solid #e2e8f0;border-radius:.85rem;padding:.7rem .95rem;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)'>"
            . "<div style='font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em'>{$icon} {$label}</div>"
            . "<div style='font-size:1.45rem;font-weight:800;color:{$color};line-height:1.25;margin-top:.1rem'>{$value}</div>"
            . '</div>';

        $html = "<div style='display:flex;gap:.7rem;flex-wrap:wrap;margin-top:.35rem'>"
            . $card('Jumlah Pesanan', number_format($count, 0, ',', '.'), '#1e293b', '🧾')
            . $card('Total Omzet', $rp($omzet), '#2563eb', '💰')
            . $card('Total Laba', $rp($laba), $laba < 0 ? '#dc2626' : '#16a34a', '📈')
            . '</div>';

        return new HtmlString($html);
    }

    // Pesanan masuk lewat Import (tanpa tombol "Buat"). Aksi: isi estimasi biaya admin.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('estimasiAdmin')
                ->label('Isi Estimasi Biaya Admin')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Isi estimasi biaya admin')
                ->modalDescription('Mengisi biaya admin (estimasi dari % kategori produk) untuk pesanan yang BELUM punya biaya admin & belum final. Pesanan dengan laba final tidak diubah. Pastikan produk sudah punya kategori.')
                ->modalSubmitActionLabel('Isi estimasi sekarang')
                ->action(function (): void {
                    $res = app(AdminFeeEstimator::class)->applyToOrg((int) auth()->user()->organization_id);
                    Notification::make()
                        ->title("Estimasi terisi untuk {$res['updated']} pesanan")
                        ->body('Total estimasi biaya admin: Rp ' . number_format($res['total'], 0, ',', '.')
                            . ($res['updated'] === 0 ? ' — pastikan produk sudah dipasangi kategori.' : ''))
                        ->success()
                        ->send();
                }),
        ];
    }
}
