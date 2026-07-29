<?php

namespace BeyondCode\Mailbox\Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait SignsResendWebhooks
{
    protected function postResendWebhook(
        string $payload,
        string $webhookId = 'msg_test',
        ?int $timestamp = null,
        ?string $signature = null,
        bool $configureSecret = true
    ): TestResponse {
        $timestamp = $timestamp ?? now()->timestamp;
        $secret = 'test-secret';

        if ($configureSecret) {
            config([
                'mailbox.services.resend.webhook_secret' => 'whsec_'.base64_encode($secret),
            ]);
        }

        $signature = $signature ?? base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            $secret,
            true
        ));

        return $this->call('POST', '/laravel-mailbox/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $webhookId,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $payload);
    }
}
