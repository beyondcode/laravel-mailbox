<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\MailCareRequest;
use Illuminate\Routing\Controller;

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
