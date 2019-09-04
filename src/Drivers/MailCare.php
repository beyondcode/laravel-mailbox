<?php

namespace BeyondCode\Mailbox\Drivers;

use Illuminate\Support\Facades\Route;
use BeyondCode\Mailbox\Http\Controllers\MailCareController;

class MailCare implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/mailcare', MailCareController::class);
        });
    }
}
