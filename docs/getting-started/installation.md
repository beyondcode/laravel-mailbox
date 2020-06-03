---
title: Installation
order: 2
---

# Installation

Laravel Mailbox can be installed via composer:

```bash
composer require beyondcode/laravel-mailbox
```

The package will automatically register a service provider.

This package comes with a migration to store all incoming email messages. You can publish the migration file using:

```bash
php artisan vendor:publish --provider="BeyondCode\Mailbox\MailboxServiceProvider" --tag="migrations"
```

Run the migrations with:

```bash
php artisan migrate
```

Next, you need to publish the mailbox configuration file:

```bash
php artisan vendor:publish --provider="BeyondCode\Mailbox\MailboxServiceProvider" --tag="config"
```

This is the default content of the config file that will be published as  `config/mailbox.php`:

```php
return [

    /*
     * The driver to use when listening for incoming emails.
     * It defaults to the mail driver that you are using.
     *
     * Supported drivers: "log", "mailgun", "sendgrid"
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
    'store_incoming_emails_for_days' => 7,

    /*
     * By default, this package only stores incoming email messages
     * when they match one of your mailboxes. To store all incoming
     * messages, modify this value.
     */
    'only_store_matching_emails' => true,

    /*
     * Some services do not have their own authentication methods to
     * verify the incoming request. For these services, you need
     * to use this username and password combination for HTTP
     * basic authentication.
     *
     * See the driver specific documentation if it applies to your
     * driver.
     */
    'basic_auth' => [
        'username' => env('MAILBOX_HTTP_USERNAME', 'laravel-mailbox'),
        'password' => env('MAILBOX_HTTP_PASSWORD')
    ],

    /*
     * Third party service configuration.
     */
    'services' => [

        'mailgun' => [
            'key' => env('MAILBOX_MAILGUN_KEY'),
        ],

    ]

];
```
