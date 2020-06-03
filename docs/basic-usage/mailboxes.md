# Mailboxes

This package works by listening for incoming emails from one of the supported drivers and then "reacting" to an incoming email. This happens in custom Mailbox classes - you can think of them as custom route handlers for your emails.

## Defining Mailboxes

You can define your mailboxes in one of your Laravel service providers. For example, within the `boot` method of your `AppServiceProvider`.

```php
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Facades\Mailbox;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Mailbox::from('sender@domain.com', function (InboundEmail $email) {
            // Handle the incoming email
        });
    }
}
```

A single mailbox takes care of handling one specific kind of email. You can either define a closure as the second argument, or use an invokable class. This method/class will then get executed every time your application receives an incoming email that matches the mailbox pattern and subject.

## Invokable classes

Instead of the closure based approach, you can also pass a class name as the second argument of the mailbox create methods. This class will then be created and executed:

```php
Mailbox::from('sender@domain.com', MyMailbox::class);

class MyMailbox
{
    public function __invoke(InboundEmail $email)
    {
        // Handle the incoming email
    }
}
```

## Matching sender emails

To create a mailbox that matches a specific sender email address, you may use the `Mailbox::from` method.

This mailbox will be called whenever the sender of the email addresses matches.

```php
Mailbox::from('sender@domain.com', MyMailbox::class);
```

## Matching recipient emails

To create a mailbox that matches a specific recipient email address, you may use the `Mailbox::to` method.

This mailbox will be called whenever at least one of the email recipients matches.

```php
Mailbox::to('recipient@domain.com', MyMailbox::class);
```

## Matching CC emails

Similar to matching email recipients, you may also want to restrict your mailbox to the incoming emails CC attribute. Use the `Mailbox::cc` method for this.

This mailbox will be called whenever at least one of the cc recipients matches.

```php
Mailbox::cc('cc@domain.com', MyMailbox::class);
```

## Matching the subject

Instead of checking for the email recipients or sender you can also match against the email subject using the `Mailbox::subject` method.

This mailbox will be called whenever the email subject matches.

```php
Mailbox::subject('Feedback Request', MyMailbox::class);
```

## Catch-All

In some cases you might want to create a mailbox that receives all incoming emails, no matter what they contain.

You can use the `Mailbox::catchAll` method for this. This method only receives a closure/class name that will be called every time your application receives an email.

```php
Mailbox::catchAll(CatchAllMailbox::class);
```

## Fallback

Similar to the "catch-all" mailbox, you might also want to create a fallback mailbox that will be called when none of your other mailboxes match the incoming email. While the `catchAll` mailbox will be called for **every** incoming email, the `fallback` mailbox will only be called when no other mailbox matches.

```php
Mailbox::fallback(FallbackMailbox::class);
```

## Using Parameters

In addition to using fixed strings as your mailbox matching rules, you can also use parameters in curly braces - similar to the Laravel routing.

Just wrap the part of the matching rule that you want to capture as a parameter in curly braces and the parameter value will be passed to your invokable class / callback method.

```php
Mailbox::from('{username}@domain.com', function (InboundEmail $email, $username) {

});
```

## Regular Expression Constraints

You may constrain the format of your mailbox parameters by defining a regular expression in your mailbox definition:

```php
Mailbox::from('{username}@domain.com', function (InboundEmail $email, $username) {

})->where('username', '[A-Za-z]+')
```
