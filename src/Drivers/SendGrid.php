<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\SendGridController;
use Illuminate\Support\Facades\Route;

class SendGrid implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/sendgrid', SendGridController::class);
        });
    }
}
