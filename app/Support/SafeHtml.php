<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class SafeHtml
{
    public static function sanitize(?string $html): string
    {
        return app(HtmlSanitizerInterface::class)->sanitize($html ?? '');
    }

    public static function color(?string $color): string
    {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color ?? '') ? $color : '#6b7280';
    }
}
