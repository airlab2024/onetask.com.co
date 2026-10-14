<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketHour;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProjectHoursExport implements FromCollection, WithHeadings
{
    public Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function headings(): array
    {
        return [
            '#',
            'Ticket',
            'User',
            'Time',
            'Hours',
            'Activity',
            'Date',
        ];
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        $collection = collect();
        $this->project->tickets
            ->filter(fn($ticket) => $ticket->hours()->count())
            ->each(fn ($ticket) =>
                $ticket->hours
                    ->each(fn(TicketHour $item) => $collection->push([
                        '#' => \App\Support\ExportCell::text($item->ticket?->code),
                        'ticket' => \App\Support\ExportCell::text($item->ticket?->name),
                        'user' => \App\Support\ExportCell::text($item->user?->name),
                        'time' => $item->forHumans,
                        'hours' => number_format($item->value, 2, ',', ' '),
                        'activity' => \App\Support\ExportCell::text($item->activity?->name),
                        'date' => $item->created_at->format(__('Y-m-d g:i A')),
                    ]))
            );
        return $collection;
    }
}
