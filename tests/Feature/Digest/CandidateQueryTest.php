<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\News\CandidateQuery;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CarbonImmutable $since;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 07:00:00'));
        $this->user = User::factory()->create();
        $this->since = CarbonImmutable::now()->subDay();
    }

    public function test_requires_an_enabled_subscription_and_an_enabled_source(): void
    {
        $enabled = $this->source();
        $disabledSubscription = $this->source(subscriptionEnabled: false);
        $disabledSource = $this->source(sourceEnabled: false);
        $unsubscribed = Source::factory()->create();
        $wanted = $this->item($enabled);
        $this->item($disabledSubscription);
        $this->item($disabledSource);
        $this->item($unsubscribed);
        Item::factory()->create(['source_slug' => $enabled->slug]);

        $this->assertSame([$wanted->id], app(CandidateQuery::class)->for($this->user, $this->since)->pluck('id')->all());
    }

    public function test_uses_first_seen_on_the_subscribed_source_including_the_boundary(): void
    {
        $source = $this->source();
        $boundary = $this->item($source, $this->since);
        $boundary->update(['published_at' => now()->subMonth(), 'created_at' => now()->subMonth()]);
        $this->item($source, $this->since->subSecond());
        $unsubscribed = Source::factory()->create();
        $oldForUser = $this->item($source, $this->since->subSecond());
        $oldForUser->sources()->attach($unsubscribed, ['first_seen_at' => now()]);

        $this->assertSame([$boundary->id], app(CandidateQuery::class)->for($this->user, $this->since)->pluck('id')->all());
    }

    public function test_loads_only_the_users_enabled_sources_in_priority_order_without_duplicates(): void
    {
        $low = $this->source(position: 20);
        $high = $this->source(position: 2);
        $disabled = $this->source(subscriptionEnabled: false, position: 1);
        $disabledSource = $this->source(sourceEnabled: false, position: 0);
        $other = Source::factory()->create(['position' => 3]);
        $item = $this->item($low);
        foreach ([$high, $disabled, $disabledSource, $other] as $source) {
            $item->sources()->attach($source, ['first_seen_at' => now()]);
        }

        $items = app(CandidateQuery::class)->for($this->user, $this->since);

        $this->assertSame([$item->id], $items->pluck('id')->all());
        $this->assertTrue($items->sole()->relationLoaded('sources'));
        $this->assertSame([$high->id, $low->id], $items->sole()->sources->pluck('id')->all());
    }

    public function test_excludes_any_of_the_users_previous_post_items_but_not_another_users(): void
    {
        $source = $this->source();
        $used = $this->item($source);
        $otherUsersPick = $this->item($source);
        $post = Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-10-15', 'kind' => PostKind::Learning]);
        PostItem::factory()->create(['post_id' => $post->id, 'item_id' => $used->id]);
        PostItem::factory()->create(['post_id' => Post::factory()->create(['user_id' => User::factory()])->id, 'item_id' => $otherUsersPick->id]);
        $otherUsersPick->update(['status' => ItemStatus::Selected, 'post_id' => $post->id]);

        $this->assertSame([$otherUsersPick->id], app(CandidateQuery::class)->for($this->user, $this->since)->pluck('id')->all());
    }

    public function test_never_returns_another_users_custom_source_items_even_with_a_subscription(): void
    {
        $owner = User::factory()->create();
        $private = Source::factory()->create(['user_id' => $owner->id]);
        SourceSubscription::factory()->create(['user_id' => $this->user->id, 'source_id' => $private->id]);
        SourceSubscription::factory()->create(['user_id' => $owner->id, 'source_id' => $private->id]);
        $item = $this->item($private);

        $this->assertTrue(app(CandidateQuery::class)->for($this->user, $this->since)->isEmpty());
        $this->assertSame([$item->id], app(CandidateQuery::class)->for($owner, $this->since)->pluck('id')->all());
    }

    private function source(bool $subscriptionEnabled = true, bool $sourceEnabled = true, int $position = 10): Source
    {
        $source = Source::factory()->create(['enabled' => $sourceEnabled, 'position' => $position]);
        SourceSubscription::factory()->create(['user_id' => $this->user->id, 'source_id' => $source->id, 'enabled' => $subscriptionEnabled]);

        return $source;
    }

    private function item(Source $source, ?CarbonImmutable $seenAt = null): Item
    {
        $item = Item::factory()->create(['source_slug' => $source->slug]);
        $item->sources()->attach($source, ['first_seen_at' => $seenAt ?? now()]);

        return $item;
    }
}
