<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\MailtrapController;
use Illuminate\Support\Facades\Route;

class Mailtrap implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/mailtrap', MailtrapController::class);
        });
    }
}
