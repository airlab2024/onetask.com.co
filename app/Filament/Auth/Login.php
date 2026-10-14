<?php

namespace App\Filament\Auth;

use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class Login extends \Filament\Pages\Auth\Login
{
    protected static string $view = 'filament.auth.login';

    public function authenticate(): ?LoginResponse
    {
        abort_unless(config('system.login_form.is_enabled'), 403);
        return parent::authenticate();
    }
}
