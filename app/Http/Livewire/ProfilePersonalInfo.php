<?php

namespace App\Http\Livewire;

use Filament\Forms\Components\TextInput;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;

class ProfilePersonalInfo extends PersonalInfo
{
    protected function getEmailComponent(): TextInput
    {
        return parent::getEmailComponent()->helperText(function (): ?string {
            $pending = $this->user->getPendingEmail();
            return $pending ? __('You have a pending email verification for :email.', ['email' => $pending]) : null;
        });
    }

    public function submit(): void
    {
        $data = collect($this->form->getState())->only($this->only)->all();
        $email = $data['email'];
        unset($data['email']);
        $this->user->update($data);
        if ($email !== $this->user->email) {
            $this->user->newEmail($email);
        }
        $this->user->refresh();
        $this->form->fill($this->user->only($this->only));
        $this->sendNotification();
    }

    public function resendPending(): void
    {
        $this->user->resendPendingEmailVerificationMail();
        \App\Support\UiNotification::send('success', __('Email verification sent'));
    }
}
