<?php

namespace BeyondCode\Mailbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BeyondCode\Mailbox\Routing\MailboxGroup
 */
class MailboxGroup extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mailbox-group';
    }
}
