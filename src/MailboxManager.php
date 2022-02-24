<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Drivers\ArrayDriver;
use BeyondCode\Mailbox\Drivers\MailCare;
use BeyondCode\Mailbox\Drivers\Mailgun;
use BeyondCode\Mailbox\Drivers\Postmark;
use BeyondCode\Mailbox\Drivers\SendGrid;
use Illuminate\Support\Manager;

class MailboxManager extends Manager
{
    public function mailbox($name = null)
    {
        return $this->driver($name);
    }

    public function createMailgunDriver()
    {
        return new Mailgun;
    }

    public function createSendGridDriver()
    {
        return new SendGrid;
    }

    public function createMailCareDriver()
    {
        return new MailCare;
    }

    public function createPostmarkDriver()
    {
        return new Postmark;
    }

    /** @deprecated */
    public function createLogDriver()
    {
        return $this->createArrayDriver();
    }

    public function createArrayDriver()
    {
        return new ArrayDriver;
    }

    public function getDefaultDriver()
    {
        return $this->container['config']['mailbox.driver'];
    }
}
