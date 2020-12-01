<?php

namespace BeyondCode\Mailbox;

use BeyondCode\Mailbox\Drivers\DriverInterface;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;

class MailboxManager extends Manager
{
    /**
     * Create a new manager instance.
     *
     * @param Container $container
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->registerDrivers();
    }

    public function getDefaultDriver()
    {
        return $this->container['config']['mailbox.driver'];
    }

    protected function registerDrivers(): void
    {
        $supported = config('mailbox.supported_drivers');

        foreach ($supported as $driver => $mappedTo) {

            $callback = is_callable($mappedTo) ?
                $mappedTo : $this->registerDriverCallable($mappedTo);

            $this->extend($driver, $callback);
        }
    }

    protected function registerDriverCallable(DriverInterface $driver): Closure
    {
        return function () use ($driver) {
            return new $driver;
        };
    }
}
