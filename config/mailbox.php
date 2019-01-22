<?php

return [

    /*
     * The driver to use when listening for incoming emails.
     * It defaults to the mail driver that you are using.
     *
     * Supported drivers: "log", "mailgun"
     */
    'driver' => env('MAILBOX_DRIVER', 'log'),

    /*
     * The path for driver specific routes. This is where
     * you need to point your driver specific callbacks
     * to.
     *
     * For example: /laravel-mailbox/sendgrid/
     */
    'path' => 'laravel-mailbox',

    /*
     * The amount of days that incoming emails should be stored in your
     * application. You can use the cleanup artisan command to
     * delete all older inbound emails on a regular basis.
     */
    'store_incoming_emails_for_days' => 1,

    /*
     * Third party service configuration.
     */
    'services' => [

        'mailgun' => [
            'key' => env('MAILBOX_MAILGUN_KEY'),
        ]

    ]

];