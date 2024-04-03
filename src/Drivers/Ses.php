<?php

namespace BeyondCode\Mailbox\Drivers;

use BeyondCode\Mailbox\Http\Controllers\SesController;
use Illuminate\Support\Facades\Route;

class Ses implements DriverInterface
{
    public function register()
    {
        Route::prefix(config('mailbox.path'))->group(function () {
            Route::post('/ses', SesController::class);
        });
    }
}
