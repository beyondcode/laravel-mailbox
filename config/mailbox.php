<?php

return [

    'driver' => env('MAIL_DRIVER', 'log'),

    'path' => 'laravel-mailbox',

    'services' => [

        'mailgun' => [
            'key' => env('MAILBOX_MAILGUN_KEY'),
        ]

    ]

];