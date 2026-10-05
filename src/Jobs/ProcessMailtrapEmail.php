<?php

namespace BeyondCode\Mailbox\Jobs;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Mailtrap\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessMailtrapEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public $tries = 40;

    public $backoff = 300;

    public $timeout = 120;

    public $failOnTimeout = true;

    public function __construct(public int $inboxId, public string $messageId)
    {
    }

    public function handle(Client $client, Factory $cache): void
    {
        $repository = $cache->store(config('mailbox.services.mailtrap.cache_store'));
        $store = $repository->getStore();

        if (! $store instanceof LockProvider) {
            throw new RuntimeException('The Mailtrap cache store must support atomic locks.');
        }

        $lock = $store->lock($this->lockKey(), 180);

        if (! $lock->get()) {
            if ($this->job && ! $this->job instanceof SyncJob) {
                $this->release(30);

                return;
            }

            throw new RuntimeException('Another worker is processing this Mailtrap message.');
        }

        try {
            if ($repository->has($this->completionKey())) {
                return;
            }

            /** @var class-string<InboundEmail> $modelClass */
            $modelClass = config('mailbox.model');
            $email = $modelClass::fromMessage($client->fetchMessage($this->inboxId, $this->messageId));

            if (! $email->isValid()) {
                Log::warning('Mailtrap delivered an invalid email. The message was skipped.', [
                    'inbox_id' => $this->inboxId,
                    'message_id' => $this->messageId,
                ]);

                return;
            }

            Mailbox::callMailboxes($email);

            if (! $repository->put($this->completionKey(), true, 86400)) {
                throw new RuntimeException('The Mailtrap completion record could not be stored.');
            }
        } finally {
            $lock->release();
        }
    }

    public function completionKey(): string
    {
        return 'laravel-mailbox:mailtrap:'.hash('sha256', $this->inboxId.':'.$this->messageId).':completed';
    }

    public function lockKey(): string
    {
        return 'laravel-mailbox:mailtrap:'.hash('sha256', $this->inboxId.':'.$this->messageId).':lock';
    }
}
