<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\MailboxGroup;
use BeyondCode\Mailbox\Http\Requests\PostmarkRequest;
use Illuminate\Routing\Controller;

class PostmarkController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox-auth');
    }

    public function __invoke(PostmarkRequest $request)
    {
        MailboxGroup::run($request->email());
    }
}
