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
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');
        $email = $modelClass::fromMessage($log->message);

        Mailbox::callMailboxes($email);
    }
}
