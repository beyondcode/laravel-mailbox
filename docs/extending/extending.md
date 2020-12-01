# Extending the package

## Drivers

You can add your own drivers to extend the package stock configuration.

1. Publish the configuration with ``php artisan vendor:publish``.
1. List of currently supported drivers is listed under ``supported_drivers`` config key.
1. Create a new driver class, making sure it implements ``DriverInterface``
1. ``register()`` method should provide all the necessary steps to register 
a driver. For this package, this is usually done to expose routes for callbacks
dynamically, i.e.: 

    ```
    public function register()
    {
        Route::prefix(config('mailbox.route_prefix'))->group(function () {
            Route::post('/mailgun/mime', MailgunController::class);
        });
    }
    ```

1. Controller needs to call a mailbox, and provide an ``InboundEmail`` instance
to it:

    ```
    public function __invoke(MailgunRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
    ```
   
1. Making a form request is optional but recommended. The exposed ``email()``
method takes in input parameters and converts it to ``InboundEmail`` instance:
    
    ```
    public function email()
    {
        return InboundEmail::fromMessage($this->get('email'));
    }
    ```
