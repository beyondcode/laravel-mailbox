<?php

return [

    'driver' => env('MAIL_DRIVER', 'log'),

    'path' => 'laravel-mailbox',

    'store_incoming_emails_for_days' => 1,

    'services' => [

        'mailgun' => [
            'key' => env('MAILBOX_MAILGUN_KEY'),
        ]

    ]

];