<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\NewsCurator;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\News\NewsSelector;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Post;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class NewsSelectorTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        config(['digest.ai_providers' => 'gemini:gemini-test-flash', 'digest.profile' => 'A Laravel developer who loves MCP.']);
        foreach ([['laravel-news', 'laravel'], ['newsletters', 'devtools']] as $i => [$slug, $tag]) {
            Source::query()->create(['slug' => $slug, 'name' => $slug, 'type' => SourceType::Feed, 'url' => "https://{$slug}.test", 'tag' => $tag, 'max_items' => 10, 'position' => $i]);
        }
        Tag::query()->create(['slug' => 'ai', 'name' => 'AI']);
        $this->post = Post::query()->create(['date' => '2026-10-16', 'kind' => PostKind::News]);
    }

    public function test_marks_selected_and_skipped_and_attaches_tags(): void
    {
        $picked = $this->candidate('https://laravel-news.com/a');
        $ignored = $this->candidate('https://laravel-news.com/b');
        NewsCurator::fake([['items' => [
            ['candidate_id' => $picked->id, 'url' => $picked->url, 'title' => 'Laravel 13.18 is out', 'summary' => 'Deferred batches.', 'tags' => ['laravel', 'ai']],
        ]]])->preventStrayPrompts();

        $result = app(NewsSelector::class)->select($this->post, collect([$picked, $ignored]));

        $picked->refresh();
        $this->assertSame(ItemStatus::Selected, $picked->status);
        $this->assertSame('Laravel 13.18 is out', $picked->title);
        $this->assertSame('Deferred batches.', $picked->summary);
        $this->assertSame($this->post->id, $picked->post_id);
        $this->assertEqualsCanonicalizing(['laravel', 'ai'], $picked->tags()->pluck('slug')->all());
        $this->assertSame(ItemStatus::Skipped, $ignored->refresh()->status);
        $this->assertSame([$picked->id], $result->items->pluck('id')->all());
        $this->assertSame('gemini:gemini-test-flash', $result->model);
    }

    public function test_newsletter_pick_creates_an_item_for_its_link(): void
    {
        $email = $this->candidate('resend://email/x', 'newsletters', links: ['https://laravelshift.com/blog/notes']);
        NewsCurator::fake([['items' => [
            ['candidate_id' => $email->id, 'url' => 'https://laravelshift.com/blog/notes', 'title' => 'Upgrade notes', 'summary' => 'What changes.', 'tags' => ['laravel']],
        ]]]);

        $result = app(NewsSelector::class)->select($this->post, collect([$email]));

        $item = Item::query()->where('url', 'https://laravelshift.com/blog/notes')->firstOrFail();
        $this->assertSame('newsletters', $item->source_slug);
        $this->assertSame(ItemStatus::Selected, $item->status);
        $this->assertSame([$item->id], $result->items->pluck('id')->all());
        $this->assertSame(ItemStatus::Skipped, $email->refresh()->status);
    }

    public function test_prompt_contains_candidates_profile_and_existing_tags(): void
    {
        $candidate = $this->candidate('https://laravel-news.com/a');
        NewsCurator::fake([['items' => []]]);

        app(NewsSelector::class)->select($this->post, collect([$candidate]));

        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('https://laravel-news.com/a')
            && $prompt->contains('"id":'.$candidate->id)
            && str_contains((string) $prompt->agent->instructions(), 'A Laravel developer who loves MCP.')
            && str_contains((string) $prompt->agent->instructions(), 'ai'));
    }

    public function test_works_with_an_empty_profile(): void
    {
        config(['digest.profile' => '']);
        NewsCurator::fake([['items' => []]]);

        app(NewsSelector::class)->select($this->post, collect([$this->candidate('https://laravel-news.com/a')]));

        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => ! str_contains((string) $prompt->agent->instructions(), 'Reader profile'));
    }

    public function test_ai_failure_raises_ai_unavailable_and_changes_nothing(): void
    {
        $candidate = $this->candidate('https://laravel-news.com/a');
        NewsCurator::fake(fn () => throw new ProviderOverloadedException('overloaded'));

        try {
            app(NewsSelector::class)->select($this->post, collect([$candidate]));
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException $e) {
            $this->assertStringContainsString('overloaded', $e->getMessage());
        }

        $this->assertSame(ItemStatus::Candidate, $candidate->refresh()->status);
    }

    /**
     * @param  list<string>|null  $links
     */
    private function candidate(string $url, string $source = 'laravel-news', ?array $links = null): Item
    {
        return Item::factory()->create(['source_slug' => $source, 'url' => $url, 'url_normalized' => $url, 'links' => $links ?? [$url]]);
    }
}
