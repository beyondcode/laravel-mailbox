<?php

namespace BeyondCode\Mailbox;

use Illuminate\Support\Manager;

class MailboxManager extends Manager
{

    public function mailbox($name = null)
    {
        return $this->driver($name);
    }

    public function getDefaultDriver()
    {
        return $this->app['config']['mailbox.default'];
    }
}