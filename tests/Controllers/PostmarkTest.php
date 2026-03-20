<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PostmarkTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']['mailbox.driver'] = 'postmark';
    }

    #[Test]
    public function it_expects_to_receive_raw_email_field()
    {
        $this->withoutMiddleware();

        $this->post('/laravel-mailbox/postmark', [
            'something' => 'value',
        ])->assertSessionHasErrors('RawEmail')->assertStatus(302);

        $this->post('/laravel-mailbox/postmark', [
            'RawEmail' => 'value',
        ])->assertStatus(200);
    }
}
