<?php

namespace App\Models;

class DatabaseNotification extends \Illuminate\Notifications\DatabaseNotification
{
    protected $casts = ['data' => \App\Casts\LegacyNotificationData::class, 'read_at' => 'datetime'];
}
