<?php

namespace BeyondCode\Mailbox\Facades;

use BeyondCode\Mailbox\Routing\Router;
use Illuminate\Support\Facades\Facade;

/**
 * @see Router
 */
class Mailbox extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mailbox';
    }
}
