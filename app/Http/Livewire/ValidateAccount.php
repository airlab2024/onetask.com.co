<?php

namespace App\Http\Livewire;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ValidateAccount extends Component implements HasForms
{
    use InteractsWithForms;

    public User $user;
    public array $data = [];
    #[\Livewire\Attributes\Locked]
    public string $invitationToken;

    public function mount()
    {
        abort_unless(filled($this->user->creation_token), 403);
        $this->invitationToken = $this->user->creation_token;
        $this->form->fill();
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema($this->getFormSchema())->statePath('data');
    }

    public function render(): View
    {
        return view('livewire.validate-account');
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('password')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(255)
                ->confirmed()
                ->label(__('Account password'))
                ->placeholder(__('Choose your account password')),

            TextInput::make('password_confirmation')
                ->password()
                ->required()
                ->label(__('Password confirmation'))
                ->placeholder(__('Confirm your chosen password')),
        ];
    }

    public function validateAccount(): void
    {
        $data = $this->form->getState();
        $this->user = \App\Support\AccountActivation::activate($this->user->id, $this->invitationToken, $data['password']);
        auth()->login($this->user);
        \App\Support\UiNotification::send('success', __('Account verified'), true);
        $this->redirect(route('filament.onetask.pages.dashboard'));
    }
}
