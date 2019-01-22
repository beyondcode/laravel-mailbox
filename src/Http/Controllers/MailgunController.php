<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\MailgunRequest;

class MailgunController
{
    public function __invoke(MailgunRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
