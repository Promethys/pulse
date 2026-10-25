<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostKind;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_users_can_have_posts_for_the_same_date_and_kind(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $first->id, 'date' => '2026-10-25', 'kind' => PostKind::News]);
        $other = Post::factory()->create(['user_id' => $second->id, 'date' => '2026-10-25', 'kind' => PostKind::News]);

        $this->assertSame($first->id, $post->fresh()->user_id);
        $this->assertSame($second->id, $other->fresh()->user_id);
    }

    public function test_the_same_user_cannot_have_two_posts_for_the_same_date_and_kind(): void
    {
        $user = User::factory()->create();
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-25', 'kind' => PostKind::News]);

        $this->expectException(UniqueConstraintViolationException::class);

        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-25', 'kind' => PostKind::News]);
    }

    public function test_the_same_user_can_have_different_kinds_and_dates(): void
    {
        $user = User::factory()->create();
        $news = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-25', 'kind' => PostKind::News]);
        $learning = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-25', 'kind' => PostKind::Learning]);
        $tomorrow = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-26', 'kind' => PostKind::News]);

        $this->assertTrue($news->fresh()->user->is($user));
        $this->assertEqualsCanonicalizing([$news->id, $learning->id, $tomorrow->id], $user->posts->modelKeys());
    }

    public function test_deleting_a_user_deletes_their_posts_and_keeps_other_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $other = Post::factory()->create(['user_id' => User::factory()->create()->id]);

        $user->delete();

        $this->assertModelMissing($post);
        $this->assertModelExists($other);
    }
}
