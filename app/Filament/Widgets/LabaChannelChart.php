<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\ProfitService;
use Filament\Widgets\ChartWidget;

class LabaChannelChart extends ChartWidget
{
    protected static ?int $sort = 0;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Laba per Channel';
    }

    protected function getData(): array
    {
        $rows = Order::query()
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->selectRaw('marketplace, SUM(' . ProfitService::SQL_PROFIT . ') laba')
            ->groupBy('marketplace')
            ->pluck('laba', 'marketplace');

        $map = ['SHOPEE' => 'Shopee', 'TIKTOKTOKO' => 'Tokopedia/TikTok'];
        $labels = []; $data = []; $colors = [];
        foreach ($map as $key => $label) {
            $labels[] = $label;
            $data[] = round((float) ($rows[$key] ?? 0));
            $colors[] = $key === 'SHOPEE' ? '#f97316' : '#22c55e';
        }

        return [
            'datasets' => [['label' => 'Laba (Rp)', 'data' => $data, 'backgroundColor' => $colors]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
