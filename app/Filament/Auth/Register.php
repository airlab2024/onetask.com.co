<?php

namespace App\Filament\Auth;

use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Illuminate\Database\Eloquent\Model;

class Register extends \Filament\Pages\Auth\Register
{
    public function register(): ?RegistrationResponse
    {
        abort_unless(config('filament-breezy.enable_registration'), 403);
        return parent::register();
    }

    protected function sendEmailVerificationNotification(Model $user): void
    {
        // The Registered listener already sends the application's existing
        // first-email verification. Keep one message and its established link.
    }
}
