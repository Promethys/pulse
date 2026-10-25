<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_item_belongs_to_its_post_and_item_and_keeps_its_own_text(): void
    {
        $post = Post::factory()->create();
        $item = Item::factory()->create(['title' => 'Original title', 'summary' => 'Original summary']);
        $selection = new PostItem;
        $selection->fill(['post_id' => $post->id, 'item_id' => $item->id, 'title' => str_repeat('a', 500), 'summary' => null, 'position' => 3]);
        $selection->save();
        $selection->refresh();

        $this->assertTrue($selection->post->is($post));
        $this->assertTrue($selection->item->is($item));
        $this->assertSame(str_repeat('a', 500), $selection->title);
        $this->assertNull($selection->summary);
        $this->assertSame(3, $selection->position);
        $this->assertSame('Original title', $item->fresh()->title);
        $this->assertSame('Original summary', $item->fresh()->summary);
    }

    public function test_post_item_tags_use_a_separate_pivot_without_timestamps(): void
    {
        $selection = PostItem::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $original = Tag::factory()->create();
        $selection->item->tags()->attach($original);

        $selection->tags()->attach($tags);

        $this->assertEqualsCanonicalizing($tags->modelKeys(), $selection->fresh()->tags->modelKeys());
        foreach ($tags as $tag) {
            $this->assertDatabaseHas('post_item_tag', ['post_item_id' => $selection->id, 'tag_id' => $tag->id]);
        }
        $this->assertSame([$original->id], $selection->item->fresh()->tags->modelKeys());

        $selection->tags()->detach($tags->first());

        $this->assertSame([$tags->last()->id], $selection->fresh()->tags->modelKeys());
    }

    public function test_a_posts_selections_are_ordered_by_position(): void
    {
        $post = Post::factory()->create();
        $third = PostItem::factory()->create(['post_id' => $post->id, 'position' => 3]);
        $first = PostItem::factory()->create(['post_id' => $post->id, 'position' => 1]);
        $second = PostItem::factory()->create(['post_id' => $post->id, 'position' => 2]);
        PostItem::factory()->create(['position' => 1]);

        $this->assertSame([$first->id, $second->id, $third->id], $post->postItems->modelKeys());
    }

    public function test_the_same_item_cannot_be_selected_twice_in_one_post(): void
    {
        $selection = PostItem::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        PostItem::factory()->create(['post_id' => $selection->post_id, 'item_id' => $selection->item_id, 'position' => 2]);
    }

    public function test_the_same_item_can_be_selected_in_different_posts(): void
    {
        $item = Item::factory()->create();
        $first = PostItem::factory()->create(['item_id' => $item->id]);
        $second = PostItem::factory()->create(['item_id' => $item->id]);

        $this->assertNotSame($first->post_id, $second->post_id);
        $this->assertTrue($first->item->is($second->item));
    }

    public function test_deleting_a_post_deletes_its_selections_and_their_tag_pivots(): void
    {
        $selection = PostItem::factory()->create();
        $other = PostItem::factory()->create();
        $tag = Tag::factory()->create();
        $selection->tags()->attach($tag);
        $item = $selection->item;

        $selection->post->delete();

        $this->assertModelMissing($selection);
        $this->assertDatabaseMissing('post_item_tag', ['post_item_id' => $selection->id]);
        $this->assertModelExists($item);
        $this->assertModelExists($tag);
        $this->assertModelExists($other);
    }

    public function test_deleting_an_item_deletes_its_selections_and_keeps_posts(): void
    {
        $item = Item::factory()->create();
        $selections = PostItem::factory()->count(2)->create(['item_id' => $item->id]);
        $other = PostItem::factory()->create();
        $post = $selections->first()->post;

        $item->delete();

        $this->assertDatabaseMissing('post_items', ['item_id' => $item->id]);
        $this->assertModelExists($post);
        $this->assertModelExists($other);
    }

    public function test_deleting_a_tag_deletes_its_selection_pivot_and_keeps_the_selection(): void
    {
        $selection = PostItem::factory()->create();
        $tag = Tag::factory()->create();
        $selection->tags()->attach($tag);

        $tag->delete();

        $this->assertDatabaseMissing('post_item_tag', ['tag_id' => $tag->id]);
        $this->assertModelExists($selection);
    }
}
