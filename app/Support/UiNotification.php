<?php

namespace App\Support;

use Filament\Notifications\Notification;

class UiNotification
{
    public static function send(string $status, string $message, bool $persist = false): void
    {
        $notification = Notification::make()->title($message)->status($status);
        if ($persist) {
            $notification->persistent();
        }
        $notification->send();
    }
}
