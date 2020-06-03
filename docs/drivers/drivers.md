# Available drivers

Once you have configured your mailboxes, you need to connect your email provider with this package.
These are the supported drivers.

You can configure your driver by specifying the `MAILBOX_DRIVER` environment variable in your `.env` file.

## Mailgun

To use Laravel Mailbox with your Mailgun account, you first need to set the `MAILBOX_MAILGUN_KEY` environment variable to your [Mailgun API key](https://help.mailgun.com/hc/en-us/articles/203380100-Where-can-I-find-my-API-key-and-SMTP-credentials-).

You can then set your `MAILBOX_DRIVER` to "mailgun".

Next you will need to configure Mailgun, to send incoming emails to your application at `/laravel-mailbox/mailgun/mime`. So if your application is at `https://awesome-laravel.com`, it would be `https://awesome-laravel.com/laravel-mailbox/mailgun/mime`.

See ["Receiving, Forwarding and Storing Messages"](https://documentation.mailgun.com/en/latest/user_manual.html#receiving-forwarding-and-storing-messages) in the Mailgun documentation.

## Postmark

::: warning
To use Postmark with Laravel Mailbox, you will need to generate a random password and store it as the `MAILBOX_HTTP_PASSWORD` environment variable. The default username is "laravel-mailbox", but you can change it using the `MAILBOX_HTTP_USERNAME` environment variable. 
:::

You can then set your `MAILBOX_DRIVER` to "postmark".

Next you will need to configure Postmark, to send incoming emails to your application at `/laravel-mailbox/postmark`. Use the username and the password that you generated for the URL. 

If your application is at `https://awesome-laravel.com`, it would be `https://laravel-mailbox:YOUR-GENERATED-PASSWORD@awesome-laravel.com/laravel-mailbox/postmark`.

See the official ["Postmark documentation"](https://postmarkapp.com/manual#configure-your-inbound-webhook-url).

::: tip
Be sure the check the box labeled "Include raw email content in JSON payload" when setting up Postmark.
:::

## SendGrid

::: warning
To use SendGrid with Laravel Mailbox, you will need to generate a random password and store it as the `MAILBOX_HTTP_PASSWORD` environment variable. The default username is "laravel-mailbox", but you can change it using the `MAILBOX_HTTP_USERNAME` environment variable. 
:::

You can then set your `MAILBOX_DRIVER` to "sendgrid".

Next you will need to configure SendGrid Inbound parse, to send incoming emails to your application at `/laravel-mailbox/sendgrid`. Use the username and the password that you generated for the URL. 

If your application is at `https://awesome-laravel.com`, it would be `https://laravel-mailbox:YOUR-GENERATED-PASSWORD@awesome-laravel.com/laravel-mailbox/sendgrid`.

See ["SendGrid Inbound Parse"](https://sendgrid.com/docs/for-developers/parsing-email/setting-up-the-inbound-parse-webhook/).

::: tip
Be sure the check the box labeled "Post the raw, full MIME message." when setting up SendGrid.
:::

## MailCare

You can then set your `MAILBOX_DRIVER` to "mailcare".

Next you will need to configure MailCare, to send incoming emails to your application at `/laravel-mailbox/mailcare`:
- Activate authentication and automation features.
- Create a new automation with the URL `https://your-application.com/laravel-mailbox/mailcare`
- Be sure the check the box labeled "Post the raw, full MIME message."

See ["MailCare"](https://mailcare.io) for more information.

## Local development / log driver

When working locally, you might not want to use real incoming emails while testing your application. Out of the box, this package supports Laravel's "log" mail driver for incoming emails.

To test incoming emails, set both your `MAIL_DRIVER` and your `MAILBOX_DRIVER`  in your `.env` file to "log".
Now every time you send an email in your application, this email will appear in your `laravel.log` file and will try to call one of your configured Mailboxes.
