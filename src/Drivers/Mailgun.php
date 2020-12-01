<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\MailgunController;
use Illuminate\Support\Facades\Route;

class Mailgun implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.route_path'))->group(function () {
            Route::post('/mailgun/mime', MailgunController::class);
        });
    }
}
