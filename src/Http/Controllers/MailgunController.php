<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\MailboxGroup;
use BeyondCode\Mailbox\Http\Requests\MailgunRequest;

class MailgunController
{
    public function __invoke(MailgunRequest $request)
    {
        MailboxGroup::run($request->email());
    }
}
