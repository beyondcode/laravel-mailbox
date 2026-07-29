<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Middleware\MailboxBasicAuthentication;
use BeyondCode\Mailbox\Routing\Router;
use Illuminate\Contracts\Foundation\CachesConfiguration;
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
        $configPath = __DIR__.'/../config/mailbox.php';

        $this->mergeConfigFrom($configPath, 'mailbox');
        $this->mergeResendConfigFrom($configPath);

        $this->app->singleton('mailbox', function () {
            return new Router($this->app);
        });

        $this->app->singleton(MailboxManager::class, function () {
            return new MailboxManager($this->app);
        });
    }

    protected function mergeResendConfigFrom(string $path)
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $defaults = require $path;
        $services = $config->get('mailbox.services', []);

        if (! is_array($services) ||
            (array_key_exists('resend', $services) && ! is_array($services['resend']))) {
            return;
        }

        $services['resend'] = array_merge(
            $defaults['services']['resend'],
            $services['resend'] ?? []
        );

        $config->set('mailbox.services', $services);
    }

    protected function registerDriver()
    {
        Mailbox::mailbox()->register();
    }
}
