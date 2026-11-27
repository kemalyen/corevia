<?php

namespace App\Filament\Widgets;

use App\Models\SalesOrder;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DailyOrdersChart extends ChartWidget
{
    protected ?string $heading = 'Daily Orders (Last 14 Days)';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', SalesOrder::class) ?? false;
    }

    protected function getData(): array
    {
        $startDate = CarbonImmutable::today()->subDays(13);

        $countsByDate = SalesOrder::query()
            ->select([
                DB::raw('DATE(order_date) as order_day'),
                DB::raw('COUNT(*) as total'),
            ])
            ->where('order_date', '>=', $startDate->startOfDay())
            ->groupBy('order_day')
            ->pluck('total', 'order_day');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $startDate->addDays($i);
            $labels[] = $day->format('M j');
            $data[] = (int) ($countsByDate[$day->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
