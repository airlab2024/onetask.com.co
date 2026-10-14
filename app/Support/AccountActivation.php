<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AccountActivation
{
    public static function activate(int $id, string $token, string $password): User
    {
        Validator::make(['password' => $password], ['password' => ['required', 'string', 'min:8', 'max:255', 'not_regex:/[\x00-\x1f\x7f]/']])->validate();
        return DB::transaction(function () use ($id, $token, $password): User {
            $user = User::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(is_string($user->creation_token) && $token !== '' && hash_equals($user->creation_token, $token), 403);
            $user->forceFill(['creation_token' => null, 'password' => Hash::make($password), 'email_verified_at' => now()])->save();
            return $user;
        }, 3);
    }
}
