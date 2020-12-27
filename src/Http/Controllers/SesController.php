<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\MailCareRequest;
use BeyondCode\Mailbox\Http\Requests\SesRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SesController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox');
    }

    public function __invoke(SesRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
