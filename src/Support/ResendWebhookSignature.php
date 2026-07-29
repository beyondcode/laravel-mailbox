<?php

namespace BeyondCode\Mailbox\Support;

use Carbon\Carbon;

class ResendWebhookSignature
{
    protected const PREFIX = 'whsec_';
    protected const TOLERANCE = 300;

    public function verify(
        string $payload,
        ?string $id,
        ?string $timestamp,
        ?string $signatures,
        ?string $secret
    ): bool {
        if (! $id || ! $timestamp || ! $signatures || ! $secret) {
            return false;
        }

        $decodedSecret = $this->decodeSecret($secret);
        $timestamp = filter_var($timestamp, FILTER_VALIDATE_INT);

        if ($decodedSecret === null || $timestamp === false) {
            return false;
        }

        $now = Carbon::now()->timestamp;

        if ($timestamp < $now - static::TOLERANCE || $timestamp > $now + static::TOLERANCE) {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $id.'.'.$timestamp.'.'.$payload,
            $decodedSecret,
            true
        ));

        foreach (preg_split('/\s+/', trim($signatures)) as $signature) {
            [$version, $value] = array_pad(explode(',', $signature, 2), 2, null);

            if ($version === 'v1' && is_string($value) && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }

    public function isValidSecret(?string $secret): bool
    {
        return $this->decodeSecret($secret) !== null;
    }

    protected function decodeSecret(?string $secret): ?string
    {
        if (! is_string($secret) || ! str_starts_with($secret, static::PREFIX)) {
            return null;
        }

        $decoded = base64_decode(substr($secret, strlen(static::PREFIX)), true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }
}
