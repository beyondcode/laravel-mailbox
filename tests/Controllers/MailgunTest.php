<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Http\Controllers\MailgunController;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class MailgunTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        Route::post('/laravel-mailbox/mailgun/mime', MailgunController::class);
    }

    /** @test */
    public function it_verifies_mailgun_signatures()
    {
        $this->post('/laravel-mailbox/mailgun/mime', [
            'body-mime' => 'mime',
            'timestamp' => 1548104992,
            'token' => 'something',
            'signature' => 'something',
        ])->assertStatus(401);

        $timestamp = time();
        $token = uniqid();

        $this->app['config']['mailbox.services.mailgun.key'] = '12345';

        $validSignature = hash_hmac('sha256', $timestamp.$token, '12345');

        $this->post('/laravel-mailbox/mailgun/mime', [
            'body-mime' => 'mime',
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $validSignature,
        ])->assertStatus(200);
    }

    /** @test */
    public function it_verifies_fresh_timestamps()
    {
        $timestamp = now()->subMinutes(5)->timestamp;
        $token = uniqid();

        $this->app['config']['mailbox.services.mailgun.key'] = '12345';

        $validSignature = hash_hmac('sha256', $timestamp.$token, '12345');

        $this->post('/laravel-mailbox/mailgun/mime', [
            'body-mime' => 'mime',
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $validSignature,
        ])->assertStatus(401);
    }
}
