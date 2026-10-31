<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Models\AiKey;
use App\Models\DeliveryChannel;
use App\Models\LearningPost;
use App\Models\Post;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_view_is_owner_only_including_for_administrators(): void
    {
        $post = Post::factory()->create();

        $this->assertTrue($post->user->can('view', $post));
        $this->assertFalse(User::factory()->create()->can('view', $post));
        $this->assertFalse(User::factory()->create(['is_admin' => true])->can('view', $post));
    }

    public function test_learning_post_view_uses_the_parent_post_owner(): void
    {
        $post = Post::factory()->create(['kind' => PostKind::Learning]);
        $learning = LearningPost::query()->create(['post_id' => $post->id, 'kind' => LearningKind::Tip, 'title' => 'Tip', 'body' => 'Body', 'links' => []]);

        $this->assertTrue($post->user->can('view', $learning));
        $this->assertFalse(User::factory()->create()->can('view', $learning));
        $this->assertFalse(User::factory()->create(['is_admin' => true])->can('view', $learning));
    }

    #[DataProvider('sourceAbilities')]
    public function test_catalog_and_custom_source_permissions(string $ability, bool $catalogAllowed): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['is_admin' => true]);
        $catalog = Source::factory()->create();
        $custom = Source::factory()->create(['user_id' => $owner->id]);

        $this->assertSame($catalogAllowed, $owner->can($ability, $catalog));
        $this->assertSame($catalogAllowed, $other->can($ability, $catalog));
        $this->assertTrue($owner->can($ability, $custom));
        $this->assertFalse($other->can($ability, $custom));
    }

    public static function sourceAbilities(): array
    {
        return ['view' => ['view', true], 'update' => ['update', false], 'delete' => ['delete', false]];
    }

    public function test_catalog_sources_are_viewable_by_guests_but_custom_sources_are_not(): void
    {
        $catalog = Source::factory()->create();
        $custom = Source::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertTrue(Gate::forUser(null)->allows('view', $catalog));
        $this->assertFalse(Gate::forUser(null)->allows('view', $custom));
        foreach (['update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser(null)->allows($ability, $catalog));
            $this->assertFalse(Gate::forUser(null)->allows($ability, $custom));
        }
    }

    #[DataProvider('ownedAbilities')]
    public function test_delivery_channel_and_ai_key_permissions_are_owner_only(string $model, string $ability): void
    {
        $owner = User::factory()->create();
        $record = $model::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($owner->can($ability, $record));
        $this->assertFalse(User::factory()->create()->can($ability, $record));
        $this->assertFalse(User::factory()->create(['is_admin' => true])->can($ability, $record));
        $this->assertFalse(Gate::forUser(null)->allows($ability, $record));
    }

    public static function ownedAbilities(): array
    {
        $cases = [];
        foreach ([DeliveryChannel::class, AiKey::class] as $model) {
            foreach (['view', 'update', 'delete'] as $ability) {
                $cases[$model.' '.$ability] = [$model, $ability];
            }
        }

        return $cases;
    }
}
