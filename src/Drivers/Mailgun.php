<?php

namespace BeyondCode\Mailbox\Drivers;

use Illuminate\Support\Facades\Route;
use BeyondCode\Mailbox\Http\Controllers\MailgunController;

class Mailgun implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/mailgun/mime', MailgunController::class);
        });
    }
}
