<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\PostmarkRequest;

class PostmarkController
{
    public function __invoke(PostmarkRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
