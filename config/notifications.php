<?php

return [

    'channels' => [
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],
    ],

    'database' => [
        'enabled' => true,
        'trigger' => 'notifications.database-notifications-trigger',
        'polling_interval' => '30s',
    ],

];
