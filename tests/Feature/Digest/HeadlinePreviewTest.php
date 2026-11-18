<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Models\Item;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use App\Reading\HeadlinePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadlinePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_orders_and_limits_recent_visible_subscribed_items(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $high = Source::factory()->create(['position' => 1, 'name' => 'High']);
        $low = Source::factory()->create(['position' => 2]);
        $disabled = Source::factory()->create();
        $private = Source::factory()->create(['user_id' => User::factory()]);
        foreach ([$high, $low, $disabled, $private] as $source) {
            SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => $source->id, 'enabled' => $source->id !== $disabled->id]);
        }
        $old = Item::factory()->create();
        $old->sources()->attach($high, ['first_seen_at' => now()->subDay()->subSecond()]);
        foreach ([$disabled, $private] as $source) {
            $item = Item::factory()->create();
            $item->sources()->attach($source, ['first_seen_at' => now()]);
        }
        $lowItem = Item::factory()->create(['published_at' => now()]);
        $lowItem->sources()->attach($low, ['first_seen_at' => now()]);
        $expected = [];
        for ($i = 0; $i < 11; $i++) {
            $item = Item::factory()->create(['published_at' => now()->subMinutes($i)]);
            $item->sources()->attach($high, ['first_seen_at' => now()->subDay()]);
            $expected[] = ['title' => $item->title, 'url' => $item->url, 'source' => 'High'];
        }

        $this->assertSame(array_slice($expected, 0, 10), app(HeadlinePreview::class)->for($user));
    }
}
