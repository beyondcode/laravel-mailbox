<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Clients\ResendClient;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Jobs\ProcessResendEmail;
use BeyondCode\Mailbox\Tests\Concerns\SignsResendWebhooks;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ResendTest extends TestCase
{
    use SignsResendWebhooks;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']['mailbox.driver'] = 'resend';
        $app['config']['mailbox.services.resend.api_key'] = 're_test';
    }

    #[Test]
    public function it_rejects_invalid_signatures_without_dispatching()
    {
        Bus::fake();

        $payload = json_encode([
            'type' => 'email.received',
            'data' => ['email_id' => 'email_123'],
        ]);

        $this->postResendWebhook($payload, signature: 'invalid')->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_rejects_missing_svix_headers_without_dispatching()
    {
        Bus::fake();
        config([
            'mailbox.services.resend.webhook_secret' => 'whsec_'.base64_encode('test-secret'),
        ]);

        $this->call(
            'POST',
            '/laravel-mailbox/resend',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"type":"email.received","data":{"email_id":"email_123"}}'
        )->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_rejects_stale_webhooks_without_dispatching()
    {
        Bus::fake();

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            timestamp: now()->timestamp - 301
        )->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_rejects_malformed_signed_json_without_dispatching()
    {
        Bus::fake();

        $this->postResendWebhook('{"type":')
            ->assertStatus(400);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_does_not_allow_unsigned_request_parameters_to_repair_malformed_json()
    {
        Bus::fake();

        $payload = '{"type":';
        $webhookId = 'msg_unsigned_query';
        $timestamp = now()->timestamp;
        $secret = 'test-secret';
        config([
            'mailbox.services.resend.webhook_secret' => 'whsec_'.base64_encode($secret),
        ]);
        $signature = base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            $secret,
            true
        ));

        $this->call(
            'POST',
            '/laravel-mailbox/resend?type=email.received&data%5Bemail_id%5D=email_unsigned',
            [
                'type' => 'email.received',
                'data' => ['email_id' => 'email_unsigned'],
            ],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_SVIX_ID' => $webhookId,
                'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
                'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
            ],
            $payload
        )->assertStatus(400);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_rejects_invalid_received_payloads()
    {
        Bus::fake();

        $this->postResendWebhook('{"type":"email.received","data":{}}')
            ->assertStatus(400);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_acknowledges_signed_unrelated_events()
    {
        Bus::fake();

        $this->postResendWebhook('{"type":"email.delivered","data":{}}')
            ->assertOk();

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_dispatches_received_emails_to_the_configured_connection()
    {
        Bus::fake();
        config(['mailbox.services.resend.queue_connection' => 'redis']);

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            'msg_123'
        )->assertOk();

        Bus::assertDispatched(ProcessResendEmail::class, function ($job) {
            return $job->emailId === 'email_123'
                && $job->connection === 'redis';
        });
    }

    #[Test]
    public function it_dispatches_repeated_webhooks_for_business_level_idempotency()
    {
        Bus::fake();
        config(['mailbox.services.resend.queue_connection' => 'redis']);
        $payload = '{"type":"email.received","data":{"email_id":"email_123"}}';

        $this->postResendWebhook($payload, 'msg_repeated')->assertOk();
        $this->postResendWebhook($payload, 'msg_repeated')->assertOk();

        Bus::assertDispatchedTimes(ProcessResendEmail::class, 2);
    }

    #[Test]
    #[DataProvider('invalidQueueConnections')]
    public function it_rejects_invalid_queue_configuration($connection)
    {
        Bus::fake();
        config(['mailbox.services.resend.queue_connection' => $connection]);

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}'
        )->assertStatus(500);

        Bus::assertNothingDispatched();
    }

    public static function invalidQueueConnections(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidRateLimits')]
    public function it_rejects_invalid_rate_limit_configuration($rateLimit)
    {
        Bus::fake();
        config(['mailbox.services.resend.rate_limit' => $rateLimit]);

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}'
        )->assertStatus(500);

        Bus::assertNothingDispatched();
    }

    public static function invalidRateLimits(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'non-numeric' => ['invalid'],
        ];
    }

    #[Test]
    #[DataProvider('invalidWebhookSecrets')]
    public function it_reports_invalid_webhook_secret_configuration_as_a_server_error($secret)
    {
        Bus::fake();
        config(['mailbox.services.resend.webhook_secret' => $secret]);
        $payload = '{"type":"email.received","data":{"email_id":"email_123"}}';

        $this->postResendWebhook(
            $payload,
            configureSecret: false
        )->assertStatus(500);

        Bus::assertNothingDispatched();
    }

    public static function invalidWebhookSecrets(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
            'missing prefix' => ['test-secret'],
            'bad Base64' => ['whsec_%%%'],
            'empty decoded value' => ['whsec_'],
        ];
    }

    #[Test]
    public function it_reports_a_well_formed_nonmatching_webhook_secret_as_unauthorized()
    {
        Bus::fake();
        config([
            'mailbox.services.resend.webhook_secret' => 'whsec_'.base64_encode('other-secret'),
        ]);

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            configureSecret: false
        )->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    #[Test]
    #[DataProvider('missingApiKeys')]
    public function it_rejects_missing_api_keys_before_acknowledging_an_async_webhook($apiKey)
    {
        Bus::fake();
        config([
            'mailbox.services.resend.api_key' => $apiKey,
            'mailbox.services.resend.queue_connection' => 'redis',
        ]);

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}'
        )->assertStatus(500);

        Bus::assertNothingDispatched();
    }

    public static function missingApiKeys(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'blank' => ['   '],
        ];
    }

    #[Test]
    public function it_reports_queue_dispatch_failures_as_server_errors()
    {
        config([
            'mailbox.services.resend.queue_connection' => 'resend-async',
            'queue.connections.resend-async' => ['driver' => 'null'],
        ]);

        $original = $this->app->make(Dispatcher::class);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('queue unavailable'));

        $this->app->instance(Dispatcher::class, $dispatcher);

        try {
            $this->postResendWebhook(
                '{"type":"email.received","data":{"email_id":"email_123"}}'
            )->assertStatus(500);
        } finally {
            $this->app->instance(Dispatcher::class, $original);
        }
    }

    #[Test]
    public function it_processes_raw_mime_and_attachments_on_the_sync_connection()
    {
        $downloadUrl = 'https://inbound-cdn.resend.com/raw/email?signature=test';
        $mime = file_get_contents(__DIR__.'/../Fixtures/resend-email.eml');
        $handled = false;

        config([
            'mailbox.services.resend.api_key' => 're_test',
            'mailbox.services.resend.queue_connection' => 'sync',
        ]);

        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response([
                'raw' => ['download_url' => $downloadUrl],
            ]),
            $downloadUrl => Http::response($mime),
        ]);

        Mailbox::to('inbox@example.com', function (InboundEmail $email) use (&$handled) {
            $handled = true;

            $this->assertSame('Inbound from Resend', $email->subject());
            $this->assertSame('Hello from Resend.', trim($email->text()));
            $this->assertCount(1, $email->attachments());
        });

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            'msg_123'
        )->assertOk();

        $this->assertTrue($handled);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_returns_a_server_error_without_calling_mailboxes_when_sync_retrieval_fails()
    {
        $handled = false;

        config([
            'mailbox.services.resend.api_key' => 're_test',
            'mailbox.services.resend.queue_connection' => 'sync',
        ]);

        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response('', 500),
        ]);

        Mailbox::to('inbox@example.com', function () use (&$handled) {
            $handled = true;
        });

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            'msg_failure'
        )->assertStatus(500);

        $this->assertFalse($handled);
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_honors_the_configured_model()
    {
        $downloadUrl = 'https://inbound-cdn.resend.com/raw/email?signature=test';

        config([
            'mailbox.model' => TestResendControllerInboundEmail::class,
            'mailbox.services.resend.api_key' => 're_test',
            'mailbox.services.resend.queue_connection' => 'sync',
        ]);

        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response([
                'raw' => ['download_url' => $downloadUrl],
            ]),
            $downloadUrl => Http::response('raw mime'),
        ]);

        Mailbox::shouldReceive('callMailboxes')
            ->once()
            ->with(Mockery::type(TestResendControllerInboundEmail::class));

        $this->postResendWebhook(
            '{"type":"email.received","data":{"email_id":"email_123"}}',
            'msg_model'
        )->assertOk();

        $this->assertSame('raw mime', TestResendControllerInboundEmail::$rawMessage);
    }
}

class TestResendControllerInboundEmail extends InboundEmail
{
    public static ?string $rawMessage = null;

    public static function fromMessage($message)
    {
        static::$rawMessage = $message;

        return parent::fromMessage($message);
    }
}
