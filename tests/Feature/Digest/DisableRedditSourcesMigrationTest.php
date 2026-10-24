<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableRedditSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disables_only_reddit_sources_and_restores_them_on_rollback(): void
    {
        foreach (['r-laravel', 'r-claudeai', 'r-localllama', 'r-other'] as $slug) {
            Source::query()->create([
                'slug' => $slug,
                'name' => $slug,
                'type' => SourceType::Feed,
                'url' => "https://{$slug}.test/feed",
                'tag' => 'ai',
                'max_items' => 10,
                'position' => 1,
            ]);
        }

        $migration = require database_path('migrations/2026_11_13_000001_disable_reddit_sources.php');
        $migration->up();

        foreach (['r-laravel', 'r-claudeai', 'r-localllama'] as $slug) {
            $this->assertFalse(Source::query()->where('slug', $slug)->firstOrFail()->enabled);
        }
        $this->assertTrue(Source::query()->where('slug', 'r-other')->firstOrFail()->enabled);
        $this->assertSame(4, Source::query()->count());

        $migration->down();

        $this->assertSame(4, Source::query()->enabled()->count());
    }
}
