<?php

namespace BeyondCode\Mailbox;

use Illuminate\Support\Collection;

class MailboxRouteCollection
{
    protected $routes = [];

    public function add(MailboxRoute $route)
    {
        $this->routes[] = $route;
    }

    public function match(InboundEmail $message): Collection
    {
        return Collection::make($this->routes)->filter(function ($route) use ($message) {
            return $route->matches($message);
        });
    }
}