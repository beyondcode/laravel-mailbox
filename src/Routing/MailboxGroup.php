<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;

class MailboxGroup
{
    protected $mailboxes = [];

    protected $stopAfterFirstMatch = false;

    public function add(Mailbox $mailbox)
    {
        $this->mailboxes[] = $mailbox;
    }

    public function callMailboxes(InboundEmail $email): void
    {
        /**
         * @var $mailbox Mailbox
         */
        foreach ($this->mailboxes as $mailbox) {
            $mailbox->callMailboxes($email);
        }
    }

    public function stopAfterFirstMatch(bool $stop): void
    {
        $this->stopAfterFirstMatch = $stop;
    }
}
