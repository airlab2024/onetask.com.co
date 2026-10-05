<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class LegacyFileArray implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [$value];
        }
        return is_array($decoded) ? $decoded : (is_string($decoded) ? [$decoded] : []);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : json_encode(is_array($value) ? $value : [$value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
