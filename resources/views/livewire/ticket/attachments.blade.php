<div class="space-y-6">
    @can('update', $ticket)
        <form wire:submit="perform" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">{{ __('Save attachments') }}</x-filament::button>
        </form>
    @endcan
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
