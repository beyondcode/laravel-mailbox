<?php

namespace BeyondCode\Mailbox\Tests\Mailtrap;

use BeyondCode\Mailbox\Mailtrap\Client;
use BeyondCode\Mailbox\Tests\Support\MailtrapMessage;
use BeyondCode\Mailbox\Tests\Support\MailtrapTestCase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ClientTest extends MailtrapTestCase
{
    #[Test]
    public function it_authenticates_the_api_request_and_downloads_the_original_mime_without_credentials()
    {
        $apiUrl = 'https://mailtrap.io/api/inbound/inboxes/123/messages/'.rawurlencode('id/with ?#%+');
        $rawUrl = 'https://raw.example.test/message.eml?signature=raw-secret';
        $requests = [];
        Http::fake(function (Request $request, array $options) use ($apiUrl, $rawUrl, &$requests) {
            $requests[] = $request->url();
            $this->assertSame('GET', $request->method());
            $this->assertSame(5, $options['connect_timeout']);
            $this->assertSame(20, $options['timeout']);

            if ($request->url() === $apiUrl) {
                $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-api-token'));

                return Http::response(['raw_message_url' => $rawUrl], 200, [
                    'Set-Cookie' => 'api_session=private-cookie; Path=/; Secure',
                ]);
            }

            $this->assertSame($rawUrl, $request->url());
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($request->hasHeader('Cookie'));
            $this->assertFalse($options['allow_redirects']);
            $this->assertEmpty($options['auth'] ?? null);

            return Http::response(MailtrapMessage::rich(), 200, ['Content-Type' => 'message/rfc822']);
        });

        $message = $this->app->make(Client::class)->fetchMessage(123, 'id/with ?#%+');

        $this->assertSame(MailtrapMessage::rich(), $message);
        $this->assertSame([$apiUrl, $rawUrl], $requests);
    }

    #[Test]
    #[DataProvider('missingOrUnsafeRawUrls')]
    public function it_rejects_missing_and_unsafe_raw_message_urls(array $metadata)
    {
        Http::fake(['https://mailtrap.io/api/*' => Http::response($metadata)]);

        try {
            $this->app->make(Client::class)->fetchMessage(123, 'm1');
            $this->fail('Missing or unsafe raw_message_url must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            Http::assertSentCount(1);
        }
    }

    public static function missingOrUnsafeRawUrls(): array
    {
        return [
            'missing' => [[]],
            'null' => [['raw_message_url' => null]],
            'empty' => [['raw_message_url' => '']],
            'non string' => [['raw_message_url' => ['https://raw.example.test/m.eml']]],
            'HTTP' => [['raw_message_url' => 'http://raw.example.test/m.eml']],
            'protocol relative' => [['raw_message_url' => '//raw.example.test/m.eml']],
            'relative path' => [['raw_message_url' => '/m.eml']],
            'file scheme' => [['raw_message_url' => 'file:///etc/passwd']],
            'missing host' => [['raw_message_url' => 'https:///m.eml']],
            'username' => [['raw_message_url' => 'https://user@raw.example.test/m.eml']],
            'username and password' => [['raw_message_url' => 'https://user:secret@raw.example.test/m.eml']],
        ];
    }

    #[Test]
    #[DataProvider('failedStatuses')]
    public function it_throws_on_unsuccessful_authenticated_api_requests(int $status)
    {
        Http::fake(['https://mailtrap.io/api/*' => Http::response([
            'raw_message_url' => 'https://raw.example.test/m.eml',
        ], $status)]);

        try {
            $this->app->make(Client::class)->fetchMessage(123, 'm1');
            $this->fail('A failed API request must remain retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            Http::assertSentCount(1);
            Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-api-token'));
        }
    }

    #[Test]
    #[DataProvider('failedStatuses')]
    public function it_throws_on_unsuccessful_raw_downloads_including_redirects(int $status)
    {
        Http::fake([
            'https://mailtrap.io/api/*' => Http::response([
                'raw_message_url' => 'https://raw.example.test/m.eml',
            ]),
            'https://raw.example.test/*' => Http::response('Download failed', $status, [
                'Location' => 'https://redirect.example.test/m.eml',
            ]),
        ]);

        try {
            $this->app->make(Client::class)->fetchMessage(123, 'm1');
            $this->fail('A failed or redirected download must remain retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            Http::assertSentCount(2);
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'redirect.example.test'));
        }
    }

    public static function failedStatuses(): array
    {
        return [
            'redirect' => [302],
            'unauthorized' => [401],
            'forbidden' => [403],
            'not found' => [404],
            'rate limited' => [429],
            'server error' => [500],
            'unavailable' => [503],
        ];
    }

    #[Test]
    public function it_throws_when_the_api_response_is_not_json()
    {
        Http::fake(['https://mailtrap.io/api/*' => Http::response('<html>Unavailable</html>')]);

        $this->expectException(RuntimeException::class);

        $this->app->make(Client::class)->fetchMessage(123, 'm1');
    }

    #[Test]
    #[DataProvider('connectionFailureStages')]
    public function it_throws_runtime_exceptions_for_connection_failures(bool $failDownload)
    {
        Http::fake(function (Request $request) use ($failDownload) {
            if ($failDownload && str_starts_with($request->url(), 'https://mailtrap.io/api/')) {
                return Http::response(['raw_message_url' => 'https://raw.example.test/m.eml']);
            }

            throw new ConnectionException('Timed out: https://raw.example.test/m.eml?signature=download-secret; Bearer test-api-token');
        });

        try {
            $this->app->make(Client::class)->fetchMessage(123, 'm1');
            $this->fail('Connection failures must remain retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame(RuntimeException::class, get_class($exception));
            $this->assertStringNotContainsString('download-secret', (string) $exception);
            $this->assertStringNotContainsString('test-api-token', (string) $exception);
            $this->assertStringNotContainsString('raw.example.test', (string) $exception);
            $this->assertNull($exception->getPrevious(), 'Previous transport exceptions can expose signed URLs.');
        }
    }

    public static function connectionFailureStages(): array
    {
        return ['API' => [false], 'raw download' => [true]];
    }

    #[Test]
    #[DataProvider('connectionFailureStages')]
    public function it_sanitizes_guzzle_request_exceptions_from_metadata_and_raw_downloads(bool $failDownload)
    {
        $rawUrl = 'https://raw.example.test/m.eml?signature=guzzle-download-secret';
        $attempted = [];
        Http::fake(function (Request $request) use ($failDownload, $rawUrl, &$attempted) {
            $attempted[] = $request->url();
            if ($failDownload && str_starts_with($request->url(), 'https://mailtrap.io/api/')) {
                return Http::response(['raw_message_url' => $rawUrl]);
            }

            throw new RequestException(
                'Transfer failed for '.$rawUrl.' with Bearer test-api-token',
                new PsrRequest('GET', $rawUrl, ['Authorization' => 'Bearer test-api-token'])
            );
        });

        $failure = null;
        try {
            $this->app->make(Client::class)->fetchMessage(123, 'm1');
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertNotInstanceOf(RequestException::class, $failure);
        $this->assertNull($failure->getPrevious(), 'The original request contains a signed URL and API token.');
        $this->assertStringNotContainsString($rawUrl, (string) $failure);
        $this->assertStringNotContainsString('guzzle-download-secret', (string) $failure);
        $this->assertStringNotContainsString('test-api-token', (string) $failure);
        $this->assertSame(
            $failDownload
                ? ['https://mailtrap.io/api/inbound/inboxes/123/messages/m1', $rawUrl]
                : ['https://mailtrap.io/api/inbound/inboxes/123/messages/m1'],
            $attempted
        );
    }
}
