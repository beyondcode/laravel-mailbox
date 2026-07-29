<?php

namespace BeyondCode\Mailbox\Tests\Clients;

use BeyondCode\Mailbox\Clients\ResendClient;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class ResendClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mailbox.services.resend.api_key' => 're_test']);
        Http::preventStrayRequests();
    }

    #[Test]
    public function it_retrieves_raw_email_without_forwarding_the_api_key()
    {
        $downloadUrl = 'https://inbound-cdn.resend.com/raw/email?signature=secret';
        $mime = "From: sender@example.com\r\nTo: inbox@example.com\r\n\r\nHello";

        Http::fake([
            ResendClient::API_URL.'/email%2F123' => Http::response([
                'raw' => ['download_url' => $downloadUrl],
            ]),
            $downloadUrl => Http::response($mime),
        ]);

        $this->assertSame($mime, (new ResendClient)->rawEmail('email/123'));

        Http::assertSent(function (Request $request) {
            return $request->url() === ResendClient::API_URL.'/email%2F123'
                && $request->hasHeader('Authorization', 'Bearer re_test')
                && $request->hasHeader('User-Agent', ResendClient::USER_AGENT);
        });

        Http::assertSent(function (Request $request) use ($downloadUrl) {
            return $request->url() === $downloadUrl
                && ! $request->hasHeader('Authorization');
        });
    }

    #[Test]
    public function it_requires_an_api_key()
    {
        config(['mailbox.services.resend.api_key' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resend API key is not configured.');

        (new ResendClient)->rawEmail('email_123');
    }

    #[Test]
    #[DataProvider('metadataFailureStatuses')]
    public function it_throws_for_metadata_failures_without_exposing_the_api_key(int $status)
    {
        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response('', $status),
        ]);

        $exception = null;

        try {
            (new ResendClient)->rawEmail('email_123');
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertStringNotContainsString('re_test', (string) $exception);
        Http::assertSentCount(1);
    }

    public static function metadataFailureStatuses(): array
    {
        return [
            'redirect' => [302],
            'unauthorized' => [401],
            'not found' => [404],
            'rate limited' => [429],
            'server error' => [500],
        ];
    }

    #[Test]
    #[DataProvider('invalidDownloadUrls')]
    public function it_rejects_invalid_raw_download_urls($downloadUrl)
    {
        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response([
                'raw' => ['download_url' => $downloadUrl],
            ]),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Resend did not return a valid raw email download URL.');

        (new ResendClient)->rawEmail('email_123');
    }

    public static function invalidDownloadUrls(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'malformed' => ['not a url'],
            'http' => ['http://example.com/raw'],
            'credentials' => ['https://user:pass@example.com/raw'],
        ];
    }

    #[Test]
    #[DataProvider('rawDownloadFailures')]
    public function it_sanitizes_raw_download_failures(int $status, string $body)
    {
        $downloadUrl = 'https://inbound-cdn.resend.com/raw/email?signature=secret';

        Http::fake([
            ResendClient::API_URL.'/email_123' => Http::response([
                'raw' => ['download_url' => $downloadUrl],
            ]),
            $downloadUrl => Http::response(
                $body,
                $status,
                $status === 302 ? ['Location' => 'https://example.com/redirected'] : []
            ),
        ]);

        try {
            (new ResendClient)->rawEmail('email_123');
            $this->fail('Expected raw download failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to download the raw Resend email.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('signature=secret', (string) $exception);
        }

        Http::assertSentCount(2);
    }

    public static function rawDownloadFailures(): array
    {
        return [
            'empty success' => [200, ''],
            'redirect' => [302, 'redirect'],
            'server error' => [500, 'download failed'],
        ];
    }
}
