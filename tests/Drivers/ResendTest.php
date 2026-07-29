<?php

namespace BeyondCode\Mailbox\Tests\Drivers;

use BeyondCode\Mailbox\Drivers\Resend;
use BeyondCode\Mailbox\Http\Controllers\ResendController;
use BeyondCode\Mailbox\MailboxManager;
use BeyondCode\Mailbox\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResendTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']['mailbox.driver'] = 'resend';
    }

    #[Test]
    public function it_creates_the_resend_driver()
    {
        $this->assertInstanceOf(
            Resend::class,
            $this->app->make(MailboxManager::class)->driver('resend')
        );
    }

    #[Test]
    public function it_registers_the_resend_webhook_route()
    {
        $route = collect($this->app['router']->getRoutes())->first(function ($route) {
            return $route->uri() === 'laravel-mailbox/resend';
        });

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertSame(ResendController::class.'@__invoke', $route->getAction('uses'));
        $this->assertNotContains('laravel-mailbox', $route->gatherMiddleware());
    }

    #[Test]
    public function it_uses_the_configured_mailbox_path()
    {
        config(['mailbox.path' => 'custom-mailbox']);

        (new Resend)->register();

        $route = collect($this->app['router']->getRoutes())->first(function ($route) {
            return $route->uri() === 'custom-mailbox/resend';
        });

        $this->assertNotNull($route);
    }
}
