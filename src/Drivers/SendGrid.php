<?php

namespace BeyondCode\Mailbox\Drivers;

use Illuminate\Support\Facades\Route;
use BeyondCode\Mailbox\Http\Controllers\SendGridController;

class SendGrid implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/sendgrid', SendGridController::class);
        });
    }
}
