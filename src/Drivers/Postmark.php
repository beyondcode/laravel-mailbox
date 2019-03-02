<?php

namespace BeyondCode\Mailbox\Drivers;

use Illuminate\Support\Facades\Route;
use BeyondCode\Mailbox\Http\Controllers\PostmarkController;

class Postmark implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/postmark', PostmarkController::class);
        });
    }
}
