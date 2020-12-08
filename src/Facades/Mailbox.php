<?php

namespace BeyondCode\Mailbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BeyondCode\Mailbox\Routing\Mailbox
 */
class Mailbox extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mailbox';
    }
}
