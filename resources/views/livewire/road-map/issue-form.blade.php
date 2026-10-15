<form wire:submit="submit" class="space-y-4">
    {{ $this->form }}
    <div class="flex gap-3">
        <x-filament::button type="submit">{{ __('Save') }}</x-filament::button>
        <x-filament::button type="button" color="gray" wire:click="cancel">{{ __('Cancel') }}</x-filament::button>
    </div>
</form>
