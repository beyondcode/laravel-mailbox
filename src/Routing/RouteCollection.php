<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Collection;

class RouteCollection
{
    protected $routes = [];

    public function add(Route $route)
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
