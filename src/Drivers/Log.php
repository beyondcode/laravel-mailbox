<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Facades\Mailbox;
use Illuminate\Log\Events\MessageLogged;

class Log
{

    public function processLog(MessageLogged $log)
    {
        $email = new InboundEmail([
            'message' => $log->message
        ]);

        if ($email->isValid()) {
            Mailbox::callMailboxes($email);
        }
    }
}