<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasAvatarUrl
{
    public function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            if (config('filament-avatar.default_provider') === 'gravatar') {
                return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->email ?? '')));
            }

            return 'https://ui-avatars.com/api/?' . http_build_query([
                'name' => $this->name,
                'color' => 'ffffff',
                'background' => '2563eb',
                'size' => 128,
            ]);
        });
    }
}
