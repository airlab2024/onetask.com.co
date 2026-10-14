<x-filament-panels::layout.base :title="__('Verify my account')">
    @livewire('validate-account', ['user' => $user])
</x-filament-panels::layout.base>
