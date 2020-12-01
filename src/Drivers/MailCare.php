<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\MailCareController;
use Illuminate\Support\Facades\Route;

class MailCare implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.route_path'))->group(function () {
            Route::post('/mailcare', MailCareController::class);
        });
    }
}
