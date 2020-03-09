<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\PostmarkRequest;
use Illuminate\Routing\Controller;

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
