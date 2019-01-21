<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MailgunController
{
    public function __invoke(Request $request)
    {
        $this->authenticate($request);

        $email = InboundEmail::fromMessage($request->get('body-mime	'));

        Mailbox::callMailboxes($email);
    }

    protected function authenticate(Request $request)
    {
        $data = $request->timestamp.$request->token;

        $signature = hash_hmac('sha256', $data, config('mailbox.services.mailgun.key'));

        $signed = hash_equals($request->signature, $signature);

        abort_unless($signed && $this->isFresh($request->timestamp), 401, 'Invalid Mailgun signature or timestamp.');
    }

    protected function isFresh($timestamp): bool
    {
        return now()->subMinutes(2)->lte(Carbon::createFromTimestamp($timestamp));
    }
}