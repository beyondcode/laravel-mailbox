<?php

namespace BeyondCode\Mailbox\Tests\Jobs;

use BeyondCode\Mailbox\Clients\ResendClient;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Jobs\ProcessResendEmail;
use BeyondCode\Mailbox\Queue\Middleware\ResendRateLimited;
use BeyondCode\Mailbox\Tests\TestCase;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ProcessResendEmailTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TestResendInboundEmail::$rawMessage = null;

        parent::tearDown();
    }

    #[Test]
    public function it_exposes_retry_and_middleware_configuration()
    {
        Carbon::setTestNow('2026-07-28 00:00:00');

        $syncJob = (new ProcessResendEmail('email_123'))->onConnection('sync');
        $queuedJob = (new ProcessResendEmail('email_123'))->onConnection('redis');

        $this->assertSame([5, 60, 300, 1800, 7200, 18000, 36000], $queuedJob->backoff());
        $this->assertSame(now()->addDay()->timestamp, $queuedJob->retryUntil()->timestamp);
        $this->assertSame(180, $queuedJob->timeout);
        $this->assertSame([], $syncJob->middleware());
        $this->assertInstanceOf(ResendRateLimited::class, $queuedJob->middleware()[0]);
    }

    #[Test]
    public function it_skips_rate_limiting_for_a_named_sync_queue_connection()
    {
        config([
            'queue.connections.resend-inline' => ['driver' => 'sync'],
        ]);

        $job = (new ProcessResendEmail('email_123'))
            ->onConnection('resend-inline');

        $this->assertSame([], $job->middleware());
    }

    #[Test]
    public function it_builds_the_configured_model_and_calls_mailboxes()
    {
        config(['mailbox.model' => TestResendInboundEmail::class]);

        $client = Mockery::mock(ResendClient::class);
        $client->shouldReceive('rawEmail')->once()->with('email_123')->andReturn('raw mime');

        Mailbox::shouldReceive('callMailboxes')
            ->once()
            ->with(Mockery::type(TestResendInboundEmail::class));

        (new ProcessResendEmail('email_123'))->handle($client);

        $this->assertSame('raw mime', TestResendInboundEmail::$rawMessage);
    }

    #[Test]
    public function it_does_not_call_mailboxes_when_retrieval_fails()
    {
        $expected = new RuntimeException('api failed');
        $client = Mockery::mock(ResendClient::class);
        $client->shouldReceive('rawEmail')
            ->once()
            ->with('email_123')
            ->andThrow($expected);

        Mailbox::shouldReceive('callMailboxes')->never();

        try {
            (new ProcessResendEmail('email_123'))->handle($client);
            $this->fail('Expected the client exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame($expected, $exception);
        }
    }
}

class TestResendInboundEmail extends InboundEmail
{
    public static ?string $rawMessage = null;

    public static function fromMessage($message)
    {
        static::$rawMessage = $message;

        return parent::fromMessage($message);
    }
}
