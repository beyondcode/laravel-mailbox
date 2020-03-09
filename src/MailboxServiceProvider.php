<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Middleware\MailboxBasicAuthentication;
use BeyondCode\Mailbox\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MailboxServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if (! class_exists('CreateMailboxInboundEmailsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_mailbox_inbound_emails_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_mailbox_inbound_emails_table.php'),
            ], 'migrations');
        }

        $this->publishes([
            __DIR__.'/../config/mailbox.php' => config_path('mailbox.php'),
        ], 'config');

        Route::aliasMiddleware('laravel-mailbox', MailboxBasicAuthentication::class);

        $this->commands([
            Console\CleanEmails::class,
        ]);

        $this->registerDriver();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailbox.php', 'mailbox');

        $this->app->singleton('mailbox', function () {
            return new Router($this->app);
        });

        $this->app->singleton(MailboxManager::class, function () {
            return new MailboxManager($this->app);
        });
    }

    protected function registerDriver()
    {
        Mailbox::mailbox()->register();
    }
}
