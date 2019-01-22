<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Facades\Mailbox;
use Illuminate\Log\Events\MessageLogged;

class Log implements DriverInterface
{
    public function register()
    {
        app('events')->listen(MessageLogged::class, [$this, 'processLog']);
    }

    public function processLog(MessageLogged $log)
    {
        $email = InboundEmail::fromMessage($log->message);

        Mailbox::callMailboxes($email);
    }
}
