<form wire:submit="submit" class="space-y-4">
    {{ $this->form }}
    <div class="flex flex-wrap gap-3">
        <x-filament::button type="submit">{{ __('Save') }}</x-filament::button>
        <x-filament::button type="button" color="gray" wire:click="cancel">{{ __('Cancel') }}</x-filament::button>
        @if($epic->exists)
            <x-filament::button type="button" color="danger" wire:click="delete" wire:confirm="{{ __('Delete this epic?') }}">{{ __('Delete') }}</x-filament::button>
        @endif
    </div>
</form>
