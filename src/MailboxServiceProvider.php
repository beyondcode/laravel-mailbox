<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Drivers\DriverInterface;
use BeyondCode\Mailbox\Http\Middleware\MailboxBasicAuthentication;
use BeyondCode\Mailbox\Routing\Mailbox;
use BeyondCode\Mailbox\Routing\MailboxGroup;
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

        Route::aliasMiddleware('laravel-mailbox-auth', MailboxBasicAuthentication::class);

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

        $this->app->bind('mailbox', function () {
            return new Mailbox($this->app);
        });

        $this->app->singleton(MailboxManager::class);
    }

    protected function registerDriver()
    {
        /**
         * @var $manager MailboxManager
         */
        $manager = app(MailboxManager::class);

        /**
         * @var $driver DriverInterface
         */
        $driver = $manager->driver();

        $driver->register();
    }

    public function test()
    {
        // TODO: REMOVE

        $email = new InboundEmail();
        $pattern = '';
        $action = '';

        $mbGroup = new MailboxGroup();
        $mbGroup->stopAfterFirstMatch(true);

        /**
         * @var $mailbox Mailbox
         */
        $mailbox = app('mailbox');
        $mailbox->from($pattern, $action);
        $mailbox->from($pattern, $action);
        $mailbox->matchAll(true);

        /**
         * @var $mailbox2 Mailbox
         */
        $mailbox2 = app('mailbox');
        $mailbox2->from($pattern, $action);
        $mailbox2->to($pattern, $action);

        /**
         * @var $mailbox3 Mailbox
         */
        $mailbox3 = app('mailbox');
        $mailbox3->subject($pattern, $action);

        $mbGroup->add($mailbox);
        $mbGroup->add($mailbox2);
        $mbGroup->add($mailbox3);

        $mbGroup->callMailboxes($email);
    }
}
