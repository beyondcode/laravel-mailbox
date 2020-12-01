<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\PostmarkController;
use Illuminate\Support\Facades\Route;

class Postmark implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.route_path'))->group(function () {
            Route::post('/postmark', PostmarkController::class);
        });
    }
}
