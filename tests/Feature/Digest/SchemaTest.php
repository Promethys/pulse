<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\PostKind;
use App\Models\Item;
use App\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_is_unique_per_date_and_kind(): void
    {
        Post::factory()->create(['date' => '2026-10-16', 'kind' => PostKind::News]);
        Post::factory()->create(['date' => '2026-10-16', 'kind' => PostKind::Learning]);

        $this->expectException(UniqueConstraintViolationException::class);

        Post::factory()->create(['date' => '2026-10-16', 'kind' => PostKind::News]);
    }

    public function test_item_url_normalized_is_unique(): void
    {
        Item::factory()->create(['url_normalized' => 'https://example.com/a']);

        $this->expectException(UniqueConstraintViolationException::class);

        Item::factory()->create(['url_normalized' => 'https://example.com/a']);
    }
}
