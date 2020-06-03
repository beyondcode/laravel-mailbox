# Handling Inbound Emails

Once one of your mailboxes matches an incoming email, you have access to the `InboundEmail` object that references this email. This class has a lot of convenience methods for you, to access the email content.

Under the hood, this class uses the [Mail Mime Parser](https://mail-mime-parser.org) package to parse the incoming email MIME and access it.

## Available methods

### id()

If your incoming email has a `Message-Id` header field, you can access the unique message ID using `$email->id()`. If no such header field exists, a random id will be generated.

### date()

Returns a `Carbon` object of the emails `Date` header value.

### html()

This method will return the emails HTML content.

### text()

This method will return the emails text content.

### subject()

Returns the subject of the email.

### from()

Returns the senders email address.

### fromName

Returns the name of the sender.

### to()

Returns an array containing all recipient emails and names.

### cc()

Returns an array containing all cc emails and names.

### attachments()

Returns an array containing all attachments. 

For example, to store all attachments to a file on a Laravel storage:

```php
foreach ($email->attachments() as $attachment) {
  $filename = $attachment->getFilename();

  $attachment->saveContent(storage_path($filename));
}
```

### message()

Returns the raw `ZBateson\MailMimeParser\Message` message object, if you want to do additional tasks with the mail. Please refer to the [Mail Mime Parser documentation](https://mail-mime-parser.org).

## Responding to emails

The `InboundEmail` class also has two methods to make it easy for you to either reply or forward the incoming email in your application.

### Replying

To reply to the inbound email, you can use the `reply` method. It receives a [Laravel Mailable](https://laravel.com/docs/5.7/mail#generating-mailables) as a parameter, where you can customize the email that will be sent as a response.

```php
Mailbox::from('sender@domain.com', function (InboundEmail $email) {
  $email->reply(new FeedbackReceived);
});
```

### Forwarding

In addition to replying the incoming emails, you may also want to forward the email to other recipients after your automatic processing is done:

```php
Mailbox::from('sender@domain.com', function (InboundEmail $email) {
  $email->forward('recipient@otherdomain.com');
});
```

This method accepts either an array of email addresses, a user object or an email string.

## Storing emails

By default, this package stores all matched incoming emails in your database. You can define the number of days, these emails will remain in your application in the `config/mailbox.php` file.

To automatically remove older emails, this package provides an artisan command `mailbox:clean`.

Running this command will result in the deletion of all inbound emails that are older than the number of days specified in the `store_incoming_emails_for_days` setting of the config file.

You can leverage Laravel's scheduler to run the clean up command now and then.

```php
//app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('mailbox:clean')->daily();
}
```
