<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use Illuminate\Routing\Controller;
use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\PostmarkRequest;

class PostmarkController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox');
    }

    public function __invoke(PostmarkRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
