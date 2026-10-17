<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Models\Tag;
use Database\Seeders\TagTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_core_tags_with_display_names(): void
    {
        $this->seed(TagTableSeeder::class);

        $this->assertSame(
            ['ai' => 'AI', 'devtools' => 'Dev tools', 'engineering' => 'Engineering', 'laravel' => 'Laravel', 'mcp' => 'MCP', 'php' => 'PHP'],
            Tag::query()->orderBy('slug')->pluck('name', 'slug')->all(),
        );
    }

    public function test_reseeding_never_changes_existing_rows(): void
    {
        $this->seed(TagTableSeeder::class);
        Tag::query()->where('slug', 'ai')->update(['name' => 'Artificial Intelligence', 'discord_tag_id' => '123']);
        Tag::query()->create(['slug' => 'rust', 'name' => 'Rust']);

        $this->seed(TagTableSeeder::class);

        $ai = Tag::query()->where('slug', 'ai')->firstOrFail();
        $this->assertSame('Artificial Intelligence', $ai->name);
        $this->assertSame('123', $ai->discord_tag_id);
        $this->assertSame(7, Tag::query()->count());
    }

    public function test_display_name_reads_the_tag_table_then_falls_back_to_headline(): void
    {
        $this->seed(TagTableSeeder::class);

        $this->assertSame('AI', Tag::displayName('ai'));
        $this->assertSame('Dev Tips', Tag::displayName('dev-tips'));
    }
}
