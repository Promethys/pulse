<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPasswordTest extends TestCase
{
    #[DataProvider('passwords')]
    public function test_has_password_is_a_boolean_appended_without_exposing_the_password(?string $password, bool $expected): void
    {
        $user = User::factory()->make(['password' => $password]);

        $this->assertSame($expected, $user->has_password);
        $this->assertArrayHasKey('has_password', $user->toArray());
        $this->assertSame($expected, $user->toArray()['has_password']);
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public static function passwords(): array
    {
        return ['passwordless' => [null, false], 'password' => ['known-password', true]];
    }
}
