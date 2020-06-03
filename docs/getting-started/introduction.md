---
title: Introduction
order: 1
---

# Laravel Mailbox 📫
Catch incoming emails in your Laravel application.

Laravel Mailbox is a package for Laravel 5.7 and up that will allow your application to catch and react to incoming emails from different services like Mailgun, SendGrid or the local log driver for debugging purposes.

Listen to incoming email messages in a Laravel-Route like fashion and react to them.

```php
Mailbox::from('marcel@beyondco.de', function (InboundEmail $email) {
	$subject = $email->subject();
});
```