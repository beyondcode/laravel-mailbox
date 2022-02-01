<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Mail\Events\MessageSent;

class ArrayDriver implements DriverInterface
{
    public function register()
    {
        app('events')->listen(MessageSent::class, [$this, 'processArray']);
    }

    public function processArray(MessageSent $event)
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');
        $email = $modelClass::fromMessage($event->message);

        Mailbox::callMailboxes($email);
    }
}
