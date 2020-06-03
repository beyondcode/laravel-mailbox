<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Mail\Events\MessageSent;

class Log implements DriverInterface
{
    public function register()
    {
        app('events')->listen(MessageSent::class, [$this, 'processLog']);
    }

    public function processLog(MessageSent $event)
    {
        if (config('mail.driver') !== 'log' && config('mail.default') !== 'log') {
            return;
        }

        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');
        $email = $modelClass::fromMessage($event->message);

        Mailbox::callMailboxes($email);
    }
}
