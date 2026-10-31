<?php

declare(strict_types=1);

namespace Tests\Feature\Discord;

use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscordGuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_user_cannot_install_the_same_guild_twice(): void
    {
        $user = User::factory()->create();
        DiscordGuild::factory()->create(['user_id' => $user->id, 'guild_id' => '456']);

        $this->expectException(UniqueConstraintViolationException::class);

        DiscordGuild::factory()->create(['user_id' => $user->id, 'guild_id' => '456']);
    }

    public function test_different_users_can_install_the_same_guild(): void
    {
        $first = DiscordGuild::factory()->create(['user_id' => User::factory()->create()->id, 'guild_id' => '456']);
        $second = DiscordGuild::factory()->create(['user_id' => User::factory()->create()->id, 'guild_id' => '456']);

        $this->assertModelExists($first);
        $this->assertModelExists($second);
        $this->assertNotSame($first->user_id, $second->user_id);
        $this->assertDatabaseCount('discord_guilds', 2);
    }

    public function test_guild_attributes_can_be_mass_assigned_and_relations_are_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $first = DiscordGuild::query()->create(['user_id' => $user->id, 'guild_id' => '456', 'name' => 'Developers']);
        $second = DiscordGuild::factory()->create(['user_id' => $user->id, 'guild_id' => '789']);
        DiscordGuild::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertInstanceOf(BelongsTo::class, $first->user());
        $this->assertInstanceOf(HasMany::class, $user->discordGuilds());
        $this->assertTrue($first->fresh()->user->is($user));
        $this->assertSame('456', $first->fresh()->guild_id);
        $this->assertSame('Developers', $first->fresh()->name);
        $this->assertNotNull($first->fresh()->created_at);
        $this->assertNotNull($first->fresh()->updated_at);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $user->discordGuilds->modelKeys());
    }

    public function test_deleting_a_user_cascades_to_their_guilds_and_keeps_other_users_installs(): void
    {
        $user = User::factory()->create();
        $guild = DiscordGuild::factory()->create(['user_id' => $user->id]);
        $other = DiscordGuild::factory()->create(['user_id' => User::factory()->create()->id]);

        User::query()->whereKey($user->id)->delete();

        $this->assertModelMissing($guild);
        $this->assertModelExists($other);
    }
}
