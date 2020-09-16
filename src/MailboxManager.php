<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Drivers\Log;
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

    public function createLogDriver()
    {
        return new Log;
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

    public function getDefaultDriver()
    {
        return $this->container['config']['mailbox.driver'];
    }
}
