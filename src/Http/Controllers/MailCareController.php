<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use Illuminate\Routing\Controller;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\MailCareRequest;

class MailCareController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox');
    }

    public function __invoke(MailCareRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
