<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use Illuminate\Routing\Controller;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\SendGridRequest;

class SendGridController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox');
    }

    public function __invoke(SendGridRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
