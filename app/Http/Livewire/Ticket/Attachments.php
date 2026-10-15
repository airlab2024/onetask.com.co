<?php

namespace App\Http\Livewire\Ticket;

use App\Models\Ticket;
use Filament\Facades\Filament;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class Attachments extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public array $data = [];

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public Ticket $ticket;

    protected $listeners = [
        'filesUploaded'
    ];

    public function mount(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $this->ticket);
        $this->form->fill();
    }

    public function render()
    {
        return view('livewire.ticket.attachments');
    }

    protected function getFormModel(): Model|string|null
    {
        return $this->ticket;
    }

    protected function getFormSchema(): array
    {
        return [
            SpatieMediaLibraryFileUpload::make('attachments')
                ->disk('task_files')
                ->mediaName(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) =>
                    mb_substr(preg_replace('/[\x00-\x1f\x7f]/', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255))
                ->label(__('Attachments'))
                ->helperText(__('Here you can attach all files needed for this ticket'))
                ->multiple()
                ->maxSize(10240)
                ->maxFiles(20)
                ->acceptedFileTypes([
                    'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain',
                    'application/msword', 'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->getUploadedFileNameForStorageUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file) =>
                    \Illuminate\Support\Str::uuid() . '.' . ($file->guessExtension() ?: 'bin'))
                ->disablePreview()
                ->getUploadedFileUsing(function ($component, string $file): ?array {
                    $media = $this->ticket->media()->where('uuid', $file)->first();
                    return $media ? ['name' => $media->name, 'size' => $media->size, 'type' => $media->mime_type,
                        'url' => route('tickets.attachments', ['ticket' => $this->ticket, 'media' => $media])] : null;
                })
        ];
    }

    public function perform(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $this->ticket);
        $this->form->getState();
        $this->form->fill();
        $this->dispatch('filesUploaded');
        \App\Support\UiNotification::send('success', __('Ticket attachments saved'));
    }

    public function filesUploaded(): void
    {
        $this->ticket->refresh();
    }

    protected function getTableQuery(): Builder
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $this->ticket);
        return $this->ticket->media()->getQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->sortable()
                ->searchable(),

            TextColumn::make('human_readable_size')
                ->label(__('Size'))
                ->sortable()
                ->searchable(),

            TextColumn::make('mime_type')
                ->label(__('Mime type'))
                ->sortable()
                ->searchable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            \Filament\Tables\Actions\Action::make('download')
                ->label(__('Download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn ($record) => route('tickets.attachments', ['ticket' => $this->ticket, 'media' => $record]))
                ->openUrlInNewTab(),
            DeleteAction::make()
                ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('update', $this->ticket))
                ->action(function ($record) {
                    \Illuminate\Support\Facades\Gate::authorize('update', $this->ticket);
                    abort_unless($this->ticket->media()->whereKey($record->id)->exists(), 404);
                    $record->delete();
                    \App\Support\UiNotification::send('success', __('Ticket attachment deleted'));
                })
        ];
    }
}
