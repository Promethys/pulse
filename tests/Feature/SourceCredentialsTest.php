<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SourceCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_and_hidden(): void
    {
        $credentials = ['token' => 'private-token'];
        $source = Source::factory()->create(['credentials' => $credentials]);
        $stored = DB::table('sources')->where('id', $source->id)->value('credentials');

        $this->assertIsString($stored);
        $this->assertNotSame(json_encode($credentials), $stored);
        $this->assertStringNotContainsString('private-token', $stored);
        $this->assertSame($credentials, $source->fresh()->credentials);
        $this->assertArrayNotHasKey('credentials', $source->fresh()->toArray());
    }
}
