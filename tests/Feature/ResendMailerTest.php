<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailerTest extends TestCase
{
    public function test_the_resend_mailer_resolves_with_the_api_key(): void
    {
        config(['services.resend.key' => 're_test_key']);

        $transport = Mail::mailer('resend')->getSymfonyTransport();

        $this->assertSame('resend', (string) $transport);
    }
}
