<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\ResendController;
use Illuminate\Support\Facades\Route;

class Resend implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/resend', ResendController::class);
        });
    }
}
