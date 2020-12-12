<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\MailboxGroup;
use BeyondCode\Mailbox\Http\Requests\MailCareRequest;
use Illuminate\Routing\Controller;

class MailCareController extends Controller
{
    public function __construct()
    {
        $this->middleware('laravel-mailbox-auth');
    }

    public function __invoke(MailCareRequest $request)
    {
        MailboxGroup::run($request->email());
    }
}
