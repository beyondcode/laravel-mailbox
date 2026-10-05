<?php

namespace BeyondCode\Mailbox\Tests\Support;

use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

abstract class MailtrapTestCase extends TestCase
{
    protected string $mailboxPath = 'laravel-mailbox';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']['mailbox.driver'] = 'mailtrap';
        $app['config']['mailbox.path'] = $this->mailboxPath;
        $app['config']['mailbox.services.mailtrap'] = [
            'api_token' => 'test-api-token',
            'signing_secret' => 'test-signing-secret',
            'inbox_id' => null,
            'queue_connection' => 'sync',
            'queue' => null,
            'cache_store' => null,
        ];
        $app['config']['cache.default'] = 'array';
        $app['config']['cache.stores.array'] = ['driver' => 'array'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([]);
    }

    protected function event($inboxId = 123, $messageId = 'm1'): array
    {
        return [
            'event' => 'inbound.message_received',
            'inbox_id' => $inboxId,
            'message_id' => $messageId,
        ];
    }

    protected function postEvents(array $events): TestResponse
    {
        return $this->postRaw(json_encode(['events' => $events], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    protected function postRaw(string $body, ?string $signature = null): TestResponse
    {
        return $this->call('POST', '/'.$this->mailboxPath.'/mailtrap', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MAILTRAP_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'test-signing-secret'),
        ], $body);
    }

    protected function fakeMessageDownloads(): void
    {
        Http::fake([
            'https://mailtrap.io/api/inbound/inboxes/*/messages/*' => Http::response([
                'raw_message_url' => 'https://raw.example.test/mail/message.eml?signature=download-secret',
            ]),
            'https://raw.example.test/*' => Http::response(MailtrapMessage::rich(), 200, [
                'Content-Type' => 'message/rfc822',
            ]),
        ]);
    }
}
