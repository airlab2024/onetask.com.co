<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Timesheet;

use App\Models\TicketHour;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\BarChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ActivitiesReport extends BarChartWidget
{
    protected int|string|array $columnSpan = [
        'sm' => 1,
        'md' => 6,
        'lg' => 3
    ];

    public ?string $filter = null;

    protected function getHeading(): string
    {
        return __('Logged time by activity');
    }

    protected function getFilters(): ?array
    {
        $earliest = TicketHour::where('user_id', auth()->id())->min('created_at');
        $start = $earliest ? Carbon::parse($earliest)->year : now()->year;
        $years = array_reverse(range(min($start, now()->year), now()->year));
        return array_combine($years, $years);
    }

    protected function getData(): array
    {
        $collection = $this->filter(auth()->user(), [
            'year' => $this->filter
        ]);

        $datasets = $this->getDatasets($collection);

        return [
            'datasets' => [
                [
                    'label' => __('Total time logged'),
                    'data' => $datasets['sets'],
                    'backgroundColor' => [
                        'rgba(54, 162, 235, .6)'
                    ],
                    'borderColor' => [
                        'rgba(54, 162, 235, .8)'
                    ],
                ],
            ],
            'labels' => $datasets['labels'],
        ];
    }

    protected function getDatasets(Collection $collection): array
    {
        $datasets = [
            'sets' => [],
            'labels' => []
        ];

        foreach ($collection as $item) {
            $datasets['sets'][] = $item->value;
            $datasets['labels'][] = $item->activity?->name ?? __('No activity');
        }

        return $datasets;
    }

    protected function filter(User $user, array $params): Collection
    {
        $year = $params['year'] === null ? now()->year : filter_var($params['year'], FILTER_VALIDATE_INT);
        abort_unless($year !== false && $year >= 1900 && $year <= now()->year + 1, 422);

        return TicketHour::with('activity')
            ->select([
                'activity_id',
                DB::raw('SUM(value) as value'),
            ])
            ->whereYear('created_at', $year)
            ->where('user_id', $user->id)
            ->groupBy('activity_id')
            ->get();
    }
}
