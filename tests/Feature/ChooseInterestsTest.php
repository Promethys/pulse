<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ChooseInterests;
use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ChooseInterestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_picking_interests_subscribes_only_to_enabled_default_catalog_sources(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create();
        $source = Source::factory()->create(['is_default' => true]);
        $excluded = [
            Source::factory()->create(['is_default' => true, 'enabled' => false]),
            Source::factory()->create(),
            Source::factory()->for(User::factory())->create(['is_default' => true]),
        ];
        $interest->sources()->attach([$source->id, ...array_map(fn (Source $source) => $source->id, $excluded)]);
        Source::factory()->create(['is_default' => true]);

        (new ChooseInterests)->handle($user, new Collection([$interest]));
        (new ChooseInterests)->handle($user, new Collection([$interest]));

        $this->assertSame([$source->id], $user->sourceSubscriptions()->pluck('source_id')->all());
        $this->assertTrue($user->sourceSubscriptions()->sole()->enabled);
        $this->assertSame([$interest->id], $user->interests()->pluck('interests.id')->all());
    }

    public function test_picking_again_keeps_a_disabled_subscription_disabled(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create();
        $source = Source::factory()->create(['is_default' => true]);
        $interest->sources()->attach($source);
        $subscription = $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => false]);

        (new ChooseInterests)->handle($user, new Collection([$interest]));

        $this->assertFalse($subscription->fresh()->enabled);
        $this->assertSame(1, $user->sourceSubscriptions()->count());
    }

    public function test_removing_interests_keeps_shared_disabled_and_custom_subscriptions(): void
    {
        $user = User::factory()->create();
        $removed = Interest::factory()->create();
        $remaining = Interest::factory()->create();
        $exclusive = Source::factory()->create(['is_default' => true]);
        $shared = Source::factory()->create(['is_default' => true]);
        $disabled = Source::factory()->create(['is_default' => true]);
        $custom = $user->customSources()->save(Source::factory()->make());
        $removed->sources()->attach([$exclusive->id, $shared->id, $disabled->id]);
        $remaining->sources()->attach($shared);
        $action = new ChooseInterests;
        $action->handle($user, new Collection([$removed, $remaining]));
        $user->sourceSubscriptions()->where('source_id', $disabled->id)->update(['enabled' => false]);
        $user->sourceSubscriptions()->create(['source_id' => $custom->id, 'enabled' => true]);
        $other = User::factory()->create();
        $other->sourceSubscriptions()->create(['source_id' => $exclusive->id]);
        $shared->update(['is_default' => false, 'enabled' => false]);

        $action->handle($user, new Collection([$remaining]));

        $this->assertEqualsCanonicalizing([$shared->id, $disabled->id, $custom->id], $user->sourceSubscriptions()->pluck('source_id')->all());
        $this->assertSame([$remaining->id], $user->interests()->pluck('interests.id')->all());
        $this->assertSame(1, $other->sourceSubscriptions()->count());

        $action->handle($user, new Collection);

        $this->assertSame(0, $user->interests()->count());
        $this->assertEqualsCanonicalizing([$disabled->id, $custom->id], $user->sourceSubscriptions()->pluck('source_id')->all());
    }
}
