<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\MailCareRequest;
use BeyondCode\Mailbox\Http\Requests\PostalRequest;
use Illuminate\Routing\Controller;

class PostalController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox');
    }

    public function __invoke(PostalRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
