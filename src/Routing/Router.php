<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\MailboxManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class Router
{
    use ForwardsCalls;

    /** @var RouteCollection */
    protected $routes;

    /** @var Route */
    protected $fallbackRoute;

    /** @var Route */
    protected $catchAllRoute;

    /** @var Container */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->routes = new RouteCollection;
    }

    public function from(string $pattern, $action): Route
    {
        return $this->addRoute(Route::FROM, $pattern, $action);
    }

    public function to(string $pattern, $action): Route
    {
        return $this->addRoute(Route::TO, $pattern, $action);
    }

    public function cc(string $pattern, $action): Route
    {
        return $this->addRoute(Route::CC, $pattern, $action);
    }

    public function bcc(string $pattern, $action): Route
    {
        return $this->addRoute(Route::BCC, $pattern, $action);
    }

    public function subject(string $pattern, $action): Route
    {
        return $this->addRoute(Route::SUBJECT, $pattern, $action);
    }

    public function fallback($action)
    {
        $this->fallbackRoute = $this->createRoute(Route::FALLBACK, '', $action);
    }

    public function catchAll($action)
    {
        $this->catchAllRoute = $this->createRoute(Route::CATCH_ALL, '', $action);
    }

    protected function addRoute(string $subject, string $pattern, $action): Route
    {
        $route = $this->createRoute($subject, $pattern, $action);

        $this->routes->add($route);

        return $route;
    }

    protected function createRoute(string $subject, string $pattern, $action): Route
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

            if ($matchedRoutes->isEmpty() && $this->fallbackRoute) {
                $matchedRoutes[] = $this->fallbackRoute;
                $this->fallbackRoute->run($email);
            }

            if ($this->catchAllRoute) {
                $matchedRoutes[] = $this->catchAllRoute;
                $this->catchAllRoute->run($email);
            }

            if ($this->shouldStoreInboundEmails() && $this->shouldStoreAllInboundEmails($matchedRoutes)) {
                $this->storeEmail($email);
            }
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

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(
            $this->container->make(MailboxManager::class), $method, $parameters
        );
    }
}
