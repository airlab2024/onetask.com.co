<?php

namespace App\Filament\Auth;

use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;

class Register extends \Filament\Pages\Auth\Register
{
    public function register(): ?RegistrationResponse
    {
        abort_unless(config('filament-breezy.enable_registration'), 403);
        return parent::register();
    }
}
