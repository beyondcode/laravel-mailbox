# Laravel Mailbox 📬

Handle incoming emails in your Laravel application.

``` php
Mailbox::from('{username}@gmail.com', function (InboundEmail $email, $username) {
    // Access email attributes and content
    $subject = $email->subject();
    
    $email->reply(new ReplyMailable);
});
```

## Installation

As this is a fork of beyondcode/laravel-mailbox, we need to tell composer to use the fork:  
Add the following section to your composer.json:

```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/cubewebsites/laravel-mailbox"
        }
    ],
```

Then install the package.  This should install the cubewebsites fork of laravel-mailbox

```bash
composer require cubewebsites/laravel-mailbox
```

## Usage

Take a look at the [official documentation](https://docs.beyondco.de/laravel-mailbox).

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email marcel@beyondco.de instead of using the issue tracker.

## Credits

- [Marcel Pociot](https://github.com/mpociot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
