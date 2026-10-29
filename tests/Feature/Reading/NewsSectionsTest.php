<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Digest\Rendering\NewsSections;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_input_has_no_sections(): void
    {
        $this->assertSame([], NewsSections::group([], fn (array $entry) => $entry['tags']));
    }

    public function test_untagged_entries_go_to_general_and_keep_input_order(): void
    {
        $entries = [['title' => 'Second', 'tags' => []], ['title' => 'First', 'tags' => []]];

        $this->assertSame([
            ['slug' => 'general', 'name' => Tag::displayName('general'), 'items' => $entries],
        ], NewsSections::group(collect($entries), fn (array $entry) => $entry['tags']));
    }

    public function test_most_frequent_tag_wins_and_largest_section_comes_first(): void
    {
        Tag::factory()->create(['slug' => 'php', 'name' => 'PHP']);
        Tag::factory()->create(['slug' => 'ai', 'name' => 'AI']);
        $entries = [
            ['id' => 3, 'tags' => ['ai', 'php']],
            ['id' => 2, 'tags' => ['php']],
            ['id' => 1, 'tags' => ['ai']],
            ['id' => 4, 'tags' => ['php']],
        ];

        $this->assertSame([
            ['slug' => 'php', 'name' => 'PHP', 'items' => [$entries[0], $entries[1], $entries[3]]],
            ['slug' => 'ai', 'name' => 'AI', 'items' => [$entries[2]]],
        ], NewsSections::group($entries, fn (array $entry) => $entry['tags']));
    }

    public function test_frequency_ties_use_slug_and_equal_sections_use_display_name(): void
    {
        Tag::factory()->create(['slug' => 'alpha', 'name' => 'Zulu']);
        Tag::factory()->create(['slug' => 'zulu', 'name' => 'Alpha']);
        $entries = [['tags' => ['zulu', 'alpha']], ['tags' => ['zulu']]];
        $entries[] = ['tags' => ['alpha']];
        $entries[] = ['tags' => ['zulu']];
        $entries[] = ['tags' => ['alpha']];
        $entries[] = ['tags' => []];

        $sections = NewsSections::group($entries, fn (array $entry) => $entry['tags']);

        $this->assertSame(['alpha', 'zulu', 'general'], array_column($sections, 'slug'));
        $this->assertSame([$entries[0], $entries[2], $entries[4]], $sections[0]['items']);
        $equal = NewsSections::group([['tags' => ['alpha']], ['tags' => ['zulu']]], fn (array $entry) => $entry['tags']);
        $this->assertSame(['zulu', 'alpha'], array_column($equal, 'slug'));
    }
}
