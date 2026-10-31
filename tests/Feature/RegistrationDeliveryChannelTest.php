<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryChannelType;
use App\Models\DeliveryChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationDeliveryChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_exactly_one_default_in_app_channel(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticated();
        $user = User::query()->sole();
        $channel = $user->deliveryChannels()->sole();

        $this->assertDatabaseCount('delivery_channels', 1);
        $this->assertSame($user->id, $channel->user_id);
        $this->assertTrue($channel->user->is($user));
        $this->assertSame(DeliveryChannelType::InApp, $channel->type);
        $this->assertSame('In the app', $channel->name);
        $this->assertTrue($channel->enabled);
        $this->assertEqualsCanonicalizing(['news', 'learning'], $channel->kinds);
        $this->assertNull($channel->settings);
        $this->assertDatabaseCount('digest_settings', 1);
        $this->assertNotNull($user->digestSettings);
    }

    public function test_registration_validation_failure_creates_no_channel(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, DeliveryChannel::query()->count());
        $this->assertDatabaseCount('digest_settings', 0);
    }
}
