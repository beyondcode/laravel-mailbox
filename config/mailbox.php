<?php

return [

    'driver' => env('MAIL_DRIVER', 'log'),

    'path' => 'laravel-mailbox',

    'retention_in_days' => 1,

    'services' => [

        'mailgun' => [
            'key' => env('MAILBOX_MAILGUN_KEY'),
        ]

    ]

];