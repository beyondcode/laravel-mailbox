<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Log\Logger;

class Log
{
    public function __construct(Logger $logger)
    {
        $logger->listen(function ($log) {

        });
    }

    public function processInboundEmail(InboundEmail $email)
    {
        Mailbox::callMailboxes($email);
    }
}