<?php

namespace BeyondCode\Mailbox\Jobs;

use BeyondCode\Mailbox\Clients\ResendClient;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Queue\Middleware\ResendRateLimited;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessResendEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public $timeout = 180;

    public string $emailId;

    public function __construct(string $emailId)
    {
        $this->emailId = $emailId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function backoff(): array
    {
        return [5, 60, 300, 1800, 7200, 18000, 36000];
    }

    public function middleware(): array
    {
        return $this->usesSynchronousQueue() ? [] : [new ResendRateLimited];
    }

    public function usesSynchronousQueue(): bool
    {
        $connection = $this->connection ?: config('queue.default');
        $connections = config('queue.connections', []);

        return is_string($connection)
            && is_array($connections)
            && isset($connections[$connection])
            && is_array($connections[$connection])
            && ($connections[$connection]['driver'] ?? null) === 'sync';
    }

    public function handle(ResendClient $client): void
    {
        /** @var class-string<InboundEmail> $modelClass */
        $modelClass = config('mailbox.model');
        $email = $modelClass::fromMessage($client->rawEmail($this->emailId));

        Mailbox::callMailboxes($email);
    }
}
