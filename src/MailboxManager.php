<?php

namespace BeyondCode\Mailbox;

use Illuminate\Support\Manager;
use BeyondCode\Mailbox\Drivers\Log;
use BeyondCode\Mailbox\Drivers\Mailgun;
use BeyondCode\Mailbox\Drivers\SendGrid;

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

    public function getDefaultDriver()
    {
        return $this->app['config']['mailbox.driver'];
    }
}
