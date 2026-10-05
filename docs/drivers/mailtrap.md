# Mailtrap

The Mailtrap driver connects [Mailtrap Inbound Email](https://docs.mailtrap.io/inbound-email/receiving-emails) to your Laravel mailboxes.
A webhook is an HTTP notification from Mailtrap.
The driver authenticates each webhook, downloads the original email, and passes it to your mailbox handlers.

## Setup

Mailtrap sends message identifiers in its notifications.
The driver uses an API token to retrieve the complete email, including attachments.
The webhook signing secret authenticates the original notification.

1. Create an inbound inbox in Mailtrap.
2. Create an API token with access to that inbox.
3. Create a webhook for the inbox with the JSON payload format.
4. Set the webhook URL to `https://YOUR-APPLICATION/laravel-mailbox/mailtrap`.
5. Copy the signing secret from the webhook details.
6. Add the following values to your application's `.env` file.

```dotenv
MAILBOX_DRIVER=mailtrap
MAILBOX_MAILTRAP_API_TOKEN=your-api-token
MAILBOX_MAILTRAP_SIGNING_SECRET=your-webhook-signing-secret
MAILBOX_MAILTRAP_INBOX_ID=your-inbound-inbox-id
```

Replace each placeholder with your value.
If you change `mailbox.path`, use that prefix in the webhook URL.
The driver uses the `Mailtrap-Signature` header and does not use `MAILBOX_HTTP_USERNAME` or `MAILBOX_HTTP_PASSWORD`.
It supports the JSON `events` array format.
It does not accept JSON Lines.

The inbox filter accepts one positive inbox ID without leading zeros.
If you omit `MAILBOX_MAILTRAP_INBOX_ID`, the driver accepts notifications for all inboxes available through the token.
The driver still requires a valid webhook signature.

The configuration lives under `services.mailtrap` in `config/mailbox.php`.
Older published configuration files receive missing Mailtrap defaults automatically.
If your application caches its configuration, rebuild it after these changes:

```bash
php artisan config:cache
```

If your application caches routes, rebuild them after you enable Mailtrap or change `mailbox.path`:

```bash
php artisan route:cache
```

## Background processing

The default `sync` connection processes messages during the HTTP request.
A queue worker processes messages in the background.
Use a background queue for production batches because message downloads and application handlers delay synchronous responses.
One job processes each accepted message.

The supported background queue drivers are `database`, `redis`, `sqs`, and `beanstalkd`.
On Laravel versions with `failover`, each fallback must also use a supported persistent queue.
The driver rejects `deferred`, `background`, `null`, and custom queue drivers because it cannot establish persistent retries for those connections.
Run a worker for every underlying connection in a failover setup, including nested fallback connections.
Each worker must consume the configured Mailtrap queue.
The `queue:work failover` command consumes only the first connection.

This example uses a configured `database` queue connection and a configured `redis` cache store.
Create the queue tables for your Laravel version before you start a worker.
See the [Laravel queue documentation](https://laravel.com/docs/queues) for the database prerequisites.

1. Set `retry_after` to `240` for the `database` connection in `config/queue.php`.
2. Configure the `redis` store in `config/cache.php`.
3. Add these values to your application's `.env` file.

```dotenv
MAILBOX_MAILTRAP_QUEUE_CONNECTION=database
MAILBOX_MAILTRAP_QUEUE=mailtrap
MAILBOX_MAILTRAP_CACHE_STORE=redis
```

If your application caches its configuration, rebuild it before you start the worker:

```bash
php artisan config:cache
```

Start the worker on the host that runs your application:

```bash
php artisan queue:work database --queue=mailtrap --timeout=120
```

If you change configuration or deploy new code, restart the workers:

```bash
php artisan queue:restart
```

The queue name defaults to the selected connection's queue.
The cache store defaults to the application's cache store.
The cache store must support atomic locks, which permit one worker to process a message at a time.
All web servers and workers must use the same persistent cache for duplicate protection.
The `array` cache store does not retain records between requests.

Jobs allow forty attempts, with five minutes between failed attempts.
Each job has a 120-second timeout and a 180-second processing lock.
A worker timeout moves the job to failed-job storage.
For other queue connections, set the retry interval or visibility timeout above 180 seconds.
Keep mailbox handlers below the job timeout.
The synchronous connection does not enforce a worker timeout, so configure its host to stop requests before the lock expires.

## Retries and repeated messages

The driver records successful messages in the cache for 24 hours.
It identifies each message by its Mailtrap inbox ID and message ID.
It writes the record after the mailbox handlers and any email storage complete.
Changes to email retention do not change these completion records.

If Mailtrap sends a completed message again, the driver skips its download and handlers.
If another worker holds its lock, a queued job waits thirty seconds before another attempt.
A synchronous request reports that case as a failure.
The driver attempts the other valid events in a batch even when one event fails.

Duplicate protection depends on the cache and the lock lifetime.
A cache reset, an expired record, or a failure after an application action can cause that action to repeat.
For example, a handler can send a reply and then fail before the driver records completion.
Use a persistent application key to prevent repeated actions that affect users.
The driver does not guarantee that each application action runs only once.

## Failures

Mailtrap retries unsuccessful webhook deliveries.
After the driver accepts a background job, Laravel handles that job's retries.
Configure Laravel's failed-job storage and monitor it alongside the application log.
Use `php artisan queue:failed` to inspect failed jobs before you retry them.

The webhook responses are:

- `200`: All valid events completed or entered the queue. Unrelated events, other inboxes, and malformed individual events are skipped.
- `401`: The signature is missing or invalid.
- `422`: The request does not contain a JSON object with an `events` array.
- `503`: Required configuration is invalid, a queue submission failed, or synchronous processing failed.

The driver logs a warning when it skips malformed events or invalid emails.
A missing raw download URL, an unsuccessful HTTP response, or a connection failure causes a failure that can be retried.
Each attempt retrieves a fresh download URL from Mailtrap.
The API token stays on the metadata request and does not go to the download host.

Each HTTP request has a five-second connection timeout and a twenty-second total timeout.
Downloads require HTTPS, reject embedded credentials, and do not follow redirects.
See [Mailtrap inbound webhooks](https://docs.mailtrap.io/inbound-email/webhooks) for the signature and delivery rules.
