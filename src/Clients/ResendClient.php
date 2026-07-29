<?php

namespace BeyondCode\Mailbox\Clients;

use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class ResendClient
{
    public const API_URL = 'https://api.resend.com/emails/receiving';
    public const USER_AGENT = 'beyondcode/laravel-mailbox';

    public function rawEmail(string $emailId): string
    {
        $apiKey = config('mailbox.services.resend.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new LogicException('Resend API key is not configured.');
        }

        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->withHeaders(['User-Agent' => static::USER_AGENT])
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(5)
            ->timeout(15)
            ->get(static::API_URL.'/'.rawurlencode($emailId))
            ->throw();

        if (! $response->successful()) {
            throw new RuntimeException('Unable to retrieve the Resend email.');
        }

        $downloadUrl = $response->json('raw.download_url');

        if (! $this->isHttpsUrl($downloadUrl)) {
            throw new UnexpectedValueException('Resend did not return a valid raw email download URL.');
        }

        return $this->download($downloadUrl);
    }

    protected function download(string $url): string
    {
        try {
            $response = Http::withOptions(['allow_redirects' => false])
                ->connectTimeout(5)
                ->timeout(120)
                ->get($url);

            if (! $response->successful() || $response->body() === '') {
                throw new RuntimeException;
            }

            return $response->body();
        } catch (Throwable) {
            throw new RuntimeException('Unable to download the raw Resend email.');
        }
    }

    protected function isHttpsUrl($url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
