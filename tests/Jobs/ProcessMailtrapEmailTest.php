<?php

namespace BeyondCode\Mailbox\Tests\Jobs;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Jobs\ProcessMailtrapEmail;
use BeyondCode\Mailbox\Mailtrap\Client;
use BeyondCode\Mailbox\Tests\Support\MailtrapMessage;
use BeyondCode\Mailbox\Tests\Support\MailtrapTestCase;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ProcessMailtrapEmailTest extends MailtrapTestCase
{
    protected function handle(ProcessMailtrapEmail $job): void
    {
        $job->handle($this->app->make(Client::class), $this->app->make(CacheFactory::class));
    }

    protected function fakeClient(int $calls = 1): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('fetchMessage')->with(123, 'm1')->times($calls)->andReturn(MailtrapMessage::rich());
        $this->app->instance(Client::class, $client);
    }

    #[Test]
    public function it_preserves_the_mime_message_and_uses_the_configured_model()
    {
        config(['mailbox.model' => MailtrapInboundEmail::class]);
        $this->fakeClient();
        $received = null;
        Mailbox::to('support@example.test', function (InboundEmail $email) use (&$received) {
            $received = $email;
        });

        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertInstanceOf(MailtrapInboundEmail::class, $received);
        $this->assertSame('alice@example.test', $received->from());
        $this->assertSame('Alice Sender', $received->fromName());
        $this->assertSame('support@example.test', $received->to()[0]->getEmail());
        $this->assertSame('colleague@example.test', $received->cc()[0]->getEmail());
        $this->assertSame('Re: Invoice 42', $received->subject());
        $this->assertSame('invoice-42@example.test', $received->id());
        $this->assertSame('reply@example.test', $received->headerValue('Reply-To'));
        $this->assertStringContainsString('original-42@example.test', $received->headerValue('In-Reply-To'));
        $this->assertSame(
            '<root-42@example.test> <original-42@example.test>',
            $received->message()->getHeader('References')->getRawValue()
        );
        $this->assertStringContainsString('Thanks for the invoice.', $received->text());
        $this->assertStringContainsString('> Please review invoice 42.', $received->text());
        $this->assertSame('Thanks for the invoice.', trim($received->visibleText()));
        $this->assertStringContainsString('<p>Thanks for the invoice.</p>', $received->html());
        $this->assertStringContainsString('src="cid:logo@example.test"', $received->html());

        $attachments = [];
        foreach ($received->attachments() as $attachment) {
            $attachments[$attachment->getFilename()] = $attachment;
        }
        $this->assertCount(2, $attachments);
        $this->assertSame('application/pdf', $attachments['invoice.pdf']->getContentType());
        $this->assertSame(MailtrapMessage::PDF, $attachments['invoice.pdf']->getContent());
        $this->assertSame('image/png', $attachments['logo.png']->getContentType());
        $this->assertSame(base64_decode(MailtrapMessage::PNG), $attachments['logo.png']->getContent());
        $this->assertStringContainsString('logo@example.test', $attachments['logo.png']->getHeaderValue('Content-ID'));
        $this->assertSame(1, MailtrapInboundEmail::query()->count());
        $this->assertSame(MailtrapMessage::rich(), MailtrapInboundEmail::query()->firstOrFail()->message);
    }

    #[Test]
    public function it_preserves_reply_address_and_thread_headers_when_a_handler_replies()
    {
        $this->fakeClient();
        config(['mail.default' => 'log']);
        $sent = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent) {
            $sent = $event->message;
        });
        Mailbox::to('support@example.test', function (InboundEmail $email) {
            $email->reply(new MailtrapReplyMail);
        });

        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertNotNull($sent);
        $this->assertSame('reply@example.test', $sent->getTo()[0]->getAddress());
        $this->assertSame('<invoice-42@example.test>', $sent->getHeaders()->get('In-Reply-To')->getBodyAsString());
    }

    #[Test]
    #[DataProvider('storagePolicies')]
    public function it_preserves_existing_routing_and_storage_policies(bool $matching, bool $onlyMatching, int $retention, int $stored)
    {
        $this->fakeClient();
        config([
            'mailbox.only_store_matching_emails' => $onlyMatching,
            'mailbox.store_incoming_emails_for_days' => $retention,
        ]);
        $handled = 0;
        Mailbox::to($matching ? 'support@example.test' : 'other@example.test', function () use (&$handled) {
            $handled++;
        });

        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertSame($matching ? 1 : 0, $handled);
        $this->assertSame($stored, InboundEmail::query()->count());
    }

    public static function storagePolicies(): array
    {
        return [
            'matching messages' => [true, true, 7, 1],
            'unmatched excluded' => [false, true, 7, 0],
            'all messages' => [false, false, 7, 1],
            'storage disabled' => [true, true, 0, 0],
        ];
    }

    #[Test]
    public function it_preserves_fallback_and_catch_all_handlers()
    {
        $this->fakeClient();
        $calls = [];
        Mailbox::fallback(function () use (&$calls) {
            $calls[] = 'fallback';
        });
        Mailbox::catchAll(function () use (&$calls) {
            $calls[] = 'catch-all';
        });

        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertSame(['fallback', 'catch-all'], $calls);
        $this->assertSame(1, InboundEmail::query()->count());
    }

    #[Test]
    public function it_marks_completion_only_after_the_handler_succeeds_and_skips_completed_jobs()
    {
        $this->fakeClient();
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class)->store();
        $handled = 0;
        Mailbox::to('support@example.test', function () use ($cache, $job, &$handled) {
            $this->assertFalse($cache->has($job->completionKey()));
            $handled++;
        });

        $this->handle($job);
        $this->assertTrue($cache->has($job->completionKey()));
        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertSame(1, $handled);
        $this->assertSame(1, InboundEmail::query()->count());
    }

    #[Test]
    public function it_expires_completion_after_twenty_four_hours()
    {
        $this->fakeClient(2);
        // Do not make this test depend on the application's Message-ID database index.
        config(['mailbox.store_incoming_emails_for_days' => 0]);
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class)->store();
        $handled = 0;
        Mailbox::to('support@example.test', function () use (&$handled) {
            $handled++;
        });

        try {
            $this->handle($job);
            $this->travel(86399)->seconds();
            $this->assertTrue($cache->has($job->completionKey()));
            $this->handle(new ProcessMailtrapEmail(123, 'm1'));
            $this->assertSame(1, $handled);

            $this->travel(2)->seconds();
            $this->assertFalse($cache->has($job->completionKey()));
            $this->handle(new ProcessMailtrapEmail(123, 'm1'));
            $this->assertSame(2, $handled);
        } finally {
            $this->travelBack();
        }
    }

    #[Test]
    #[DataProvider('failureStages')]
    public function it_propagates_worker_failures_releases_the_lock_and_does_not_mark_completion(string $stage)
    {
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class)->store();
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldNotReceive('release');
        $job->setJob($queueJob);
        $fail = true;
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('fetchMessage')->with(123, 'm1')->twice()->andReturnUsing(function () use ($stage, &$fail) {
            if ($stage === 'fetch' && $fail) {
                throw new RuntimeException('Temporary fetch failure');
            }

            return MailtrapMessage::rich();
        });
        $this->app->instance(Client::class, $client);
        $handled = 0;
        Mailbox::to('support@example.test', function () use ($stage, &$fail, &$handled) {
            if ($stage === 'handler' && $fail) {
                throw new RuntimeException('Temporary handler failure');
            }
            $handled++;
        });

        try {
            $this->handle($job);
            $this->fail('Worker exceptions must reach the queue worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary '.$stage.' failure', $exception->getMessage());
        }

        $this->assertFalse($cache->has($job->completionKey()));
        $this->assertSame(0, InboundEmail::query()->count());
        $probe = $cache->lock($job->lockKey(), 180);
        $this->assertTrue($probe->get(), 'The failed attempt must release its lock.');
        $probe->release();
        $fail = false;
        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertSame(1, $handled);
        $this->assertTrue($cache->has($job->completionKey()));
    }

    public static function failureStages(): array
    {
        return ['download failure' => ['fetch'], 'handler failure' => ['handler']];
    }

    #[Test]
    public function it_releases_a_queued_job_for_thirty_seconds_when_another_worker_holds_the_lock()
    {
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class)->store();
        $lock = $cache->lock($job->lockKey(), 180);
        $this->assertTrue($lock->get());
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('release')->once()->with(30);
        $job->setJob($queueJob);
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('fetchMessage');
        $this->app->instance(Client::class, $client);

        try {
            $this->handle($job);

            $this->assertFalse($cache->has($job->completionKey()));
            $this->assertFalse($cache->lock($job->lockKey(), 180)->get(), 'A competing job must not release the owner lock.');
            $this->assertSame(0, InboundEmail::query()->count());
        } finally {
            $lock->release();
        }
    }

    #[Test]
    #[DataProvider('synchronousJobs')]
    public function it_throws_a_retryable_exception_when_a_synchronous_job_encounters_a_busy_lock(bool $useSyncQueue)
    {
        $job = new ProcessMailtrapEmail(123, 'm1');
        if ($useSyncQueue) {
            $queueJob = Mockery::mock(SyncJob::class);
            $queueJob->shouldNotReceive('release');
            $job->setJob($queueJob);
        }
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('fetchMessage');
        $this->app->instance(Client::class, $client);
        $cache = $this->app->make(CacheFactory::class)->store();
        $lock = $cache->lock($job->lockKey(), 180);
        $this->assertTrue($lock->get());

        try {
            $this->handle($job);
            $this->fail('Synchronous lock contention must be retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            $this->assertFalse($cache->has($job->completionKey()));
            $this->assertFalse($cache->lock($job->lockKey(), 180)->get());
        } finally {
            $lock->release();
        }
    }

    public static function synchronousJobs(): array
    {
        return ['direct invocation' => [false], 'sync queue' => [true]];
    }

    #[Test]
    public function it_holds_an_atomic_lock_during_processing_and_expires_it_after_three_minutes()
    {
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class)->store();
        $this->fakeClient();
        Mailbox::to('support@example.test', function () use ($cache, $job) {
            $this->assertFalse($cache->lock($job->lockKey(), 180)->get());
            $this->travel(179)->seconds();
            $this->assertFalse($cache->lock($job->lockKey(), 180)->get());
            $this->travel(2)->seconds();
            $probe = $cache->lock($job->lockKey(), 180);
            $this->assertTrue($probe->get());
            $probe->release();
        });

        try {
            $this->handle($job);
        } finally {
            $this->travelBack();
        }
    }

    #[Test]
    public function it_uses_the_selected_cache_store_for_completion_and_locks()
    {
        config([
            'cache.stores.mailtrap-tests' => ['driver' => 'array'],
            'mailbox.services.mailtrap.cache_store' => 'mailtrap-tests',
        ]);
        $this->fakeClient();
        $job = new ProcessMailtrapEmail(123, 'm1');
        $cache = $this->app->make(CacheFactory::class);
        Mailbox::to('support@example.test', function () use ($cache, $job) {
            $this->assertFalse($cache->store('mailtrap-tests')->lock($job->lockKey(), 180)->get());
        });

        $this->handle($job);
        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertTrue($cache->store('mailtrap-tests')->has($job->completionKey()));
        $this->assertFalse($cache->store('array')->has($job->completionKey()));
        $probe = $cache->store('mailtrap-tests')->lock($job->lockKey(), 180);
        $this->assertTrue($probe->get());
        $probe->release();
    }

    #[Test]
    public function it_logs_and_skips_invalid_mime_without_running_handlers()
    {
        Log::spy();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('fetchMessage')->once()->with(123, 'm1')->andReturn('This is not an email.');
        $this->app->instance(Client::class, $client);
        $handled = 0;
        Mailbox::catchAll(function () use (&$handled) {
            $handled++;
        });

        $this->handle(new ProcessMailtrapEmail(123, 'm1'));

        $this->assertSame(0, $handled);
        $this->assertSame(0, InboundEmail::query()->count());
        Log::shouldHaveReceived('warning')->atLeast()->once();
        $probe = $this->app->make(CacheFactory::class)->store()->lock((new ProcessMailtrapEmail(123, 'm1'))->lockKey(), 180);
        $this->assertTrue($probe->get(), 'Skipped MIME must release its lock.');
        $probe->release();
    }

    #[Test]
    public function it_keeps_message_and_inbox_identifiers_separate_in_cache_keys()
    {
        $first = new ProcessMailtrapEmail(12, '3:m1');
        $otherInbox = new ProcessMailtrapEmail(123, 'm1');
        $otherMessage = new ProcessMailtrapEmail(12, '3:m2');
        $same = new ProcessMailtrapEmail(12, '3:m1');

        $this->assertSame($first->completionKey(), $same->completionKey());
        $this->assertSame($first->lockKey(), $same->lockKey());
        $this->assertNotSame($first->completionKey(), $first->lockKey());
        $this->assertNotSame($first->completionKey(), $otherInbox->completionKey());
        $this->assertNotSame($first->lockKey(), $otherInbox->lockKey());
        $this->assertNotSame($first->completionKey(), $otherMessage->completionKey());
    }

    #[Test]
    public function it_serializes_identifiers_and_queue_metadata_without_secrets_or_transport_objects()
    {
        $job = (new ProcessMailtrapEmail(123, 'm1'))->onConnection('redis')->onQueue('inbound-mail');
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(40, $job->tries);
        $this->assertSame(300, $job->backoff);
        $this->assertSame(120, $job->timeout);
        $this->assertTrue($job->failOnTimeout);

        $serialized = serialize($job);
        $restored = unserialize($serialized);

        $this->assertSame(123, $restored->inboxId);
        $this->assertSame('m1', $restored->messageId);
        $this->assertSame('redis', $restored->connection);
        $this->assertSame('inbound-mail', $restored->queue);
        $this->assertStringNotContainsString('test-api-token', $serialized);
        $this->assertStringNotContainsString('test-signing-secret', $serialized);
        $this->assertStringNotContainsString('https://', $serialized);
        $this->assertStringNotContainsString(Client::class, $serialized);
        Http::assertNothingSent();

        $this->fakeClient();
        $this->handle($restored);
        $this->assertTrue($this->app->make(CacheFactory::class)->store()->has($job->completionKey()));
    }
}

class MailtrapInboundEmail extends InboundEmail
{
}

class MailtrapReplyMail extends Mailable
{
    public function build()
    {
        return $this->from('support@example.test')->subject('Re: Invoice 42')->html('<p>Received.</p>');
    }
}
