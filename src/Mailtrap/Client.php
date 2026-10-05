<?php

namespace BeyondCode\Mailbox\Mailtrap;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use RuntimeException;

class Client
{
    public function __construct(protected Factory $http)
    {
    }

    public function fetchMessage(int $inboxId, string $messageId): string
    {
        $token = config('mailbox.services.mailtrap.api_token');

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('The Mailtrap API token is missing.');
        }

        try {
            $response = $this->http
                ->withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->get('https://mailtrap.io/api/inbound/inboxes/'.$inboxId.'/messages/'.rawurlencode($messageId));
        } catch (ConnectionException|TransferException $exception) {
            throw new RuntimeException('The Mailtrap message request failed during transfer.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The Mailtrap message request returned HTTP '.$response->status().'.');
        }

        $message = $response->json();
        $rawUrl = is_array($message) ? ($message['raw_message_url'] ?? null) : null;

        if (! is_string($rawUrl) || $rawUrl === '') {
            throw new RuntimeException('The Mailtrap message does not have a raw download URL.');
        }

        $url = parse_url($rawUrl);

        if ($url === false || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass'])) {
            throw new RuntimeException('The Mailtrap raw download URL must use HTTPS without credentials.');
        }

        try {
            // Use a separate request so the API token cannot reach the download host.
            $download = $this->http
                ->connectTimeout(5)
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->get($rawUrl);
        } catch (ConnectionException|TransferException $exception) {
            // Signed URLs contain credentials. Do not include the transport exception.
            throw new RuntimeException('The Mailtrap raw download failed during transfer.');
        }

        if (! $download->successful()) {
            throw new RuntimeException('The Mailtrap raw download returned HTTP '.$download->status().'.');
        }

        return $download->body();
    }
}
