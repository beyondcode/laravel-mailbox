<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Http\Requests\ResendRequest;
use BeyondCode\Mailbox\Jobs\ProcessResendEmail;
use LogicException;

class ResendController
{
    public function __invoke(ResendRequest $request)
    {
        if ($request->eventType() !== 'email.received') {
            return response('', 200);
        }

        $connection = config('mailbox.services.resend.queue_connection', 'sync');
        $rateLimit = (int) config('mailbox.services.resend.rate_limit', 5);
        $apiKey = config('mailbox.services.resend.api_key');

        if (! is_string($connection) || trim($connection) === '') {
            throw new LogicException('Resend queue connection is not configured.');
        }

        if ($rateLimit < 1) {
            throw new LogicException('Resend API rate limit must be a positive integer.');
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new LogicException('Resend API key is not configured.');
        }

        $job = (new ProcessResendEmail($request->emailId()))
            ->onConnection($connection);

        dispatch($job);

        return response('', 200);
    }
}
