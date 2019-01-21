<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Drivers\Log;
use BeyondCode\Mailbox\Http\Controllers\MailgunController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;

class MailboxServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if (! class_exists('CreateMailboxInboundEmails')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_mailbox_inbound_emails_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_mailbox_inbound_emails_table.php'),
            ], 'migrations');
        }

        $this->registerRoutes();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailbox.php', 'mailbox');

        $this->app->singleton('mailbox', function () {
            return new MailboxRouter($this->app);
        });

        if (config('mail.driver') === 'log') {
            $this->registerLogDriver();
        }
    }

    protected function registerLogDriver()
    {
        $this->app['events']->listen(MessageLogged::class, [new Log, 'processLog']);
    }

    protected function registerRoutes()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/mailgun/mime', MailgunController::class);
        });
    }
}
