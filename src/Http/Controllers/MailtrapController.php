<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Jobs\ProcessMailtrapEmail;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use JsonException;
use stdClass;
use Throwable;

class MailtrapController extends Controller
{
    public function __invoke(Request $request, Dispatcher $dispatcher): Response
    {
        $configuration = config('mailbox.services.mailtrap');
        $secret = $configuration['signing_secret'] ?? null;
        $token = $configuration['api_token'] ?? null;

        if (! is_string($secret) || trim($secret) === '' || ! is_string($token) || trim($token) === '') {
            return response('Mailtrap credentials are not configured.', 503);
        }

        $body = $request->getContent();
        $signature = $request->header('Mailtrap-Signature', '');

        if (! is_string($signature) || strlen($signature) !== 64
            || ! hash_equals(hash_hmac('sha256', $body, $secret), $signature)) {
            return response('Invalid signature.', 401);
        }

        $inboxId = $configuration['inbox_id'] ?? null;

        if ($inboxId !== null && $inboxId !== '' && ! $this->isPositiveId($inboxId)) {
            return response('The Mailtrap inbox ID must be a positive integer.', 503);
        }

        $connection = $configuration['queue_connection'] ?? 'sync';
        $queue = $configuration['queue'] ?? null;
        $cacheStore = $configuration['cache_store'] ?? null;

        if (! is_string($connection) || trim($connection) === ''
            || ($queue !== null && (! is_string($queue) || trim($queue) === ''))
            || ($cacheStore !== null && (! is_string($cacheStore) || trim($cacheStore) === ''))) {
            return response('Invalid Mailtrap queue or cache configuration.', 503);
        }

        if (! $this->isSupportedConnection($connection)) {
            return response('Use a persistent Mailtrap queue connection or sync.', 503);
        }

        try {
            $payload = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return response('The request must contain a JSON events array.', 422);
        }

        if (! $payload instanceof stdClass || ! is_array($payload->events ?? null)) {
            return response('The request must contain a JSON events array.', 422);
        }

        $failed = false;

        foreach ($payload->events as $event) {
            if (! $event instanceof stdClass || ! is_string($event->event ?? null)) {
                Log::warning('A Mailtrap event has no event type. The event was skipped.');

                continue;
            }

            $event = (array) $event;

            if ($event['event'] !== 'inbound.message_received') {
                continue;
            }

            if (! $this->isPositiveId($event['inbox_id'] ?? null)
                || ! is_string($event['message_id'] ?? null) || trim($event['message_id']) === '') {
                Log::warning('A Mailtrap event has invalid message identifiers. The event was skipped.');

                continue;
            }

            if ($inboxId !== null && $inboxId !== '' && (int) $inboxId !== (int) $event['inbox_id']) {
                continue;
            }

            $job = (new ProcessMailtrapEmail((int) $event['inbox_id'], $event['message_id']))
                ->onConnection($connection)
                ->onQueue($queue);

            try {
                $dispatcher->dispatch($job);
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('A Mailtrap message could not be processed or queued.', [
                    'inbox_id' => $job->inboxId,
                    'message_id' => $job->messageId,
                    'exception_type' => get_class($exception),
                ]);
            }
        }

        return $failed ? response('Some Mailtrap messages need another attempt.', 503) : response('OK', 200);
    }

    protected function isPositiveId($value): bool
    {
        return (is_int($value) || is_string($value))
            && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1
            && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function isSupportedConnection(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }

        $connection = config('queue.connections.'.$name);
        $driver = $connection['driver'] ?? null;

        if ($driver === 'sync') {
            return $visited === [];
        }

        if (in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return true;
        }

        if ($driver !== 'failover' || ! is_array($connection['connections'] ?? null)
            || $connection['connections'] === [] || ! array_is_list($connection['connections'])) {
            return false;
        }

        foreach ($connection['connections'] as $fallback) {
            if (! is_string($fallback) || ! $this->isSupportedConnection($fallback, [...$visited, $name])) {
                return false;
            }
        }

        return true;
    }
}
