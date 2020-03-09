<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\SendGridRequest;
use Illuminate\Routing\Controller;

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
