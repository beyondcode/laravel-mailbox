<?php

namespace BeyondCode\Mailbox\Tests\Support;

use BeyondCode\Mailbox\Support\ResendWebhookSignature;
use BeyondCode\Mailbox\Tests\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResendWebhookSignatureTest extends TestCase
{
    private const PAYLOAD = '{"type":"email.received","data":{"email_id":"email_123"}}';
    private const SECRET = 'whsec_dGVzdC1zZWNyZXQ=';
    private const TIMESTAMP = '1700000000';
    private const SIGNATURE = 'd36LOzwlBe0bKqKR8+qDaX65GyQsZLnNBqd0FeDwie8=';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp((int) self::TIMESTAMP));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_verifies_a_fixed_svix_signature()
    {
        $this->assertTrue((new ResendWebhookSignature)->verify(
            self::PAYLOAD,
            'msg_test',
            self::TIMESTAMP,
            'v1,'.self::SIGNATURE,
            self::SECRET
        ));
    }

    #[Test]
    public function it_rejects_any_raw_body_change()
    {
        $this->assertFalse((new ResendWebhookSignature)->verify(
            self::PAYLOAD.' ',
            'msg_test',
            self::TIMESTAMP,
            'v1,'.self::SIGNATURE,
            self::SECRET
        ));
    }

    #[Test]
    public function it_rejects_json_that_was_parsed_and_reserialized()
    {
        $reserialized = json_encode(
            json_decode(self::PAYLOAD, true),
            JSON_PRETTY_PRINT
        );

        $this->assertFalse((new ResendWebhookSignature)->verify(
            $reserialized,
            'msg_test',
            self::TIMESTAMP,
            'v1,'.self::SIGNATURE,
            self::SECRET
        ));
    }

    #[Test]
    public function it_accepts_any_matching_v1_signature()
    {
        $header = 'v1,invalid v2,invalid v1,'.self::SIGNATURE;

        $this->assertTrue((new ResendWebhookSignature)->verify(
            self::PAYLOAD,
            'msg_test',
            self::TIMESTAMP,
            $header,
            self::SECRET
        ));
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_signature_inputs(
        ?string $id,
        ?string $timestamp,
        ?string $signature,
        ?string $secret
    ) {
        $this->assertFalse((new ResendWebhookSignature)->verify(
            self::PAYLOAD,
            $id,
            $timestamp,
            $signature,
            $secret
        ));
    }

    public static function invalidInputs(): array
    {
        return [
            'missing id' => [null, self::TIMESTAMP, 'v1,'.self::SIGNATURE, self::SECRET],
            'missing timestamp' => ['msg_test', null, 'v1,'.self::SIGNATURE, self::SECRET],
            'missing signature' => ['msg_test', self::TIMESTAMP, null, self::SECRET],
            'missing secret' => ['msg_test', self::TIMESTAMP, 'v1,'.self::SIGNATURE, null],
            'wrong prefix' => ['msg_test', self::TIMESTAMP, 'v1,'.self::SIGNATURE, 'secret'],
            'bad base64' => ['msg_test', self::TIMESTAMP, 'v1,'.self::SIGNATURE, 'whsec_%%%'],
            'empty decoded secret' => ['msg_test', self::TIMESTAMP, 'v1,'.self::SIGNATURE, 'whsec_'],
            'wrong secret' => ['msg_test', self::TIMESTAMP, 'v1,'.self::SIGNATURE, 'whsec_b3RoZXItc2VjcmV0'],
            'malformed timestamp' => ['msg_test', 'not-an-integer', 'v1,'.self::SIGNATURE, self::SECRET],
            'wrong signature' => ['msg_test', self::TIMESTAMP, 'v1,invalid', self::SECRET],
            'unsupported version' => ['msg_test', self::TIMESTAMP, 'v2,'.self::SIGNATURE, self::SECRET],
        ];
    }

    #[Test]
    public function it_enforces_both_timestamp_boundaries()
    {
        $verifier = new ResendWebhookSignature;
        $pastBoundary = (string) (now()->timestamp - 300);
        $futureBoundary = (string) (now()->timestamp + 300);
        $tooOld = (string) (now()->timestamp - 301);
        $tooFarInFuture = (string) (now()->timestamp + 301);

        $this->assertTrue($verifier->verify(
            self::PAYLOAD,
            'msg_test',
            $pastBoundary,
            'v1,'.$this->signature($pastBoundary),
            self::SECRET
        ));
        $this->assertTrue($verifier->verify(
            self::PAYLOAD,
            'msg_test',
            $futureBoundary,
            'v1,'.$this->signature($futureBoundary),
            self::SECRET
        ));
        $this->assertFalse($verifier->verify(
            self::PAYLOAD,
            'msg_test',
            $tooOld,
            'v1,'.$this->signature($tooOld),
            self::SECRET
        ));
        $this->assertFalse($verifier->verify(
            self::PAYLOAD,
            'msg_test',
            $tooFarInFuture,
            'v1,'.$this->signature($tooFarInFuture),
            self::SECRET
        ));
    }

    private function signature(string $timestamp): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            'msg_test.'.$timestamp.'.'.self::PAYLOAD,
            'test-secret',
            true
        ));
    }
}
