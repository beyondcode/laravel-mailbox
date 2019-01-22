# Laravel Mailbox 📬

[![Latest Version on Packagist](https://img.shields.io/packagist/v/beyondcode/laravel-mailbox.svg?style=flat-square)](https://packagist.org/packages/beyondcode/laravel-mailbox)
[![Build Status](https://img.shields.io/travis/beyondcode/laravel-mailbox/master.svg?style=flat-square)](https://travis-ci.org/beyondcode/laravel-mailbox)
[![Quality Score](https://img.shields.io/scrutinizer/g/beyondcode/laravel-mailbox.svg?style=flat-square)](https://scrutinizer-ci.com/g/beyondcode/laravel-mailbox)
[![Total Downloads](https://img.shields.io/packagist/dt/beyondcode/laravel-mailbox.svg?style=flat-square)](https://packagist.org/packages/beyondcode/laravel-mailbox)

Handle incoming emails in your Laravel application.

## Installation

You can install the package via composer:

```bash
composer require beyondcode/laravel-mailbox
```

## Usage

``` php
$Mailbox = new BeyondCode\Mailbox();
echo $Mailbox->echoPhrase('Hello, BeyondCode!');
```

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