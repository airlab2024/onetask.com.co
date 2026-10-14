<?php

namespace App\Casts;

use App\Support\LegacyIcons;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class LegacyNotificationData implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        return self::normalize(json_decode($value ?? '{}', true) ?: []);
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public static function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalize($value);
            } elseif ($key === 'icon' && is_string($value)) {
                $data[$key] = LegacyIcons::name($value);
            } elseif ($key === 'color' && $value === 'secondary') {
                $data[$key] = 'gray';
            } elseif ($key === 'url' && is_string($value)) {
                $url = parse_url($value);
                $path = preg_replace('~^/onetask/public(?=/|$)~', '', $url['path'] ?? '');
                if (in_array($url['host'] ?? '', ['onetask.com.co', 'task.onetask.com.co', 'tarea.onetask.com.co', 'pruebas.onetask.com.co'], true)
                    && preg_match('~^/(tasks|tickets|projects|road-map|kanban|scrum|timesheet-dashboard)(/|$)~', $path)) {
                    $data[$key] = rtrim(config('app.url'), '/') . $path
                        . (isset($url['query']) ? '?' . $url['query'] : '')
                        . (isset($url['fragment']) ? '#' . $url['fragment'] : '');
                }
            }
        }
        return $data;
    }
}
