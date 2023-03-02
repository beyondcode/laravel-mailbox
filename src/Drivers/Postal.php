<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\MailCareController;
use BeyondCode\Mailbox\Http\Controllers\PostalController;
use Illuminate\Support\Facades\Route;

class Postal implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/postal', PostalController::class);
        });
    }
}
