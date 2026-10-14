<div class="mx-auto max-w-md space-y-6 p-6">
    <h1 class="text-xl font-semibold">{{ __('Verify my account') }}</h1>
    <form wire:submit="validateAccount" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">{{ __('Activate account') }}</x-filament::button>
    </form>
</div>
