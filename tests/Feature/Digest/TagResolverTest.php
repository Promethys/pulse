<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Support\TagResolver;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_existing_and_creates_new(): void
    {
        $ai = Tag::query()->create(['slug' => 'ai', 'name' => 'AI']);

        $tags = (new TagResolver)->resolve(['ai', 'dev-tips', 'AI']);

        $this->assertSame(['ai', 'dev-tips'], $tags->pluck('slug')->all());
        $this->assertTrue($tags->first()?->is($ai));
        $this->assertSame('Dev Tips', Tag::query()->where('slug', 'dev-tips')->value('name'));
        $this->assertSame(2, Tag::query()->count());
    }

    public function test_ignores_empty_slugs(): void
    {
        $this->assertCount(0, (new TagResolver)->resolve(['', '  ', '!!']));
    }
}
