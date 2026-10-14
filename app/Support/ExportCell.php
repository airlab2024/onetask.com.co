<?php

namespace App\Support;

class ExportCell
{
    public static function text($value): string
    {
        $value = (string) ($value ?? '-');
        // Prevent untrusted names/comments from becoming spreadsheet formulas.
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
    }
}
