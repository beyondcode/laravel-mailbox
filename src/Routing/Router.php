<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Container\Container;

class Router
{
    /** @var RouteCollection */
    protected $routes;

    /** @var Container */
    protected $container;

    public function __construct(Container $container = null)
    {
        $this->container = $container ?: new Container;

        $this->routes = new RouteCollection;
    }

    public function from(string $pattern, $action)
    {
        $this->addRoute(Route::FROM, $pattern, $action);
    }

    public function to(string $pattern, $action)
    {
        $this->addRoute(Route::TO, $pattern, $action);
    }

    public function cc(string $pattern, $action)
    {
        $this->addRoute(Route::CC, $pattern, $action);
    }

    public function subject(string $pattern, $action)
    {
        $this->addRoute(Route::SUBJECT, $pattern, $action);
    }

    protected function addRoute(string $subject, string $pattern, $action)
    {
        $this->routes->add($this->createRoute($subject, $pattern, $action));
    }

    protected function createRoute(string $subject, string $pattern, $action)
    {
        return (new Route($subject, $pattern, $action))
            ->setContainer($this->container);
    }

    public function callMailboxes(InboundEmail $email)
    {
        if ($email->isValid()) {

            if ($this->shouldStoreInboundEmails()) {
                $this->storeEmail($email);
            }

            $this->routes->match($email)->map(function (Route $route) use ($email) {
                $route->run($email);
            });
        }
    }

    protected function shouldStoreInboundEmails(): bool
    {
        return config('mailbox.store_incoming_emails_for_days') > 0;
    }

    protected function storeEmail(InboundEmail $email)
    {
        $email->save();
    }

}