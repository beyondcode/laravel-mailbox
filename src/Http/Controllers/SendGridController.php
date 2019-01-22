<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\SendGridRequest;

class SendGridController
{
    public function __invoke(SendGridRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}