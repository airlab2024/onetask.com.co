<?php

namespace App\Support;

use HtmlSanitizer\SanitizerInterface;

class SafeHtml
{
    public static function sanitize(?string $html): string
    {
        return app(SanitizerInterface::class)->sanitize($html ?? '');
    }

    public static function color(?string $color): string
    {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color ?? '') ? $color : '#6b7280';
    }
}
