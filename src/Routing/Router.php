<?php

namespace BeyondCode\Mailbox\Routing;

use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use BeyondCode\Mailbox\InboundEmail;

class Router
{
    /** @var RouteCollection */
    protected $routes;

    /** @var Route */
    protected $fallbackRoute;

    /** @var Route */
    protected $catchAllRoute;

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

    public function fallback($action)
    {
        $this->fallbackRoute = $this->createRoute(Route::FALLBACK, '', $action);
    }

    public function catchAll($action)
    {
        $this->catchAllRoute = $this->createRoute(Route::CATCH_ALL, '', $action);
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
            $matchedRoutes = $this->routes->match($email)->map(function (Route $route) use ($email) {
                $route->run($email);
            });

            if ($matchedRoutes->isEmpty()) {
                $this->callFallback($email);
            }

            $this->callCatchAll($email);

            if ($this->shouldStoreInboundEmails() && $this->shouldStoreAllInboundEmails($matchedRoutes)) {
                $this->storeEmail($email);
            }
        }
    }

    protected function callFallback(InboundEmail $email)
    {
        if ($this->fallbackRoute) {
            $this->fallbackRoute->run($email);
        }
    }

    protected function callCatchAll(InboundEmail $email)
    {
        if ($this->catchAllRoute) {
            $this->catchAllRoute->run($email);
        }
    }

    protected function shouldStoreInboundEmails(): bool
    {
        return config('mailbox.store_incoming_emails_for_days') > 0;
    }

    protected function shouldStoreAllInboundEmails(Collection $matchedRoutes): bool
    {
        return $matchedRoutes->isNotEmpty() ? true : ! config('mailbox.only_store_matching_emails');
    }

    protected function storeEmail(InboundEmail $email)
    {
        $email->save();
    }
}
