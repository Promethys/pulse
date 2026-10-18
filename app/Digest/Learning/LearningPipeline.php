<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Ai\Agents\LearningWriter;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Ai\ProviderChain;
use App\Digest\Discord\ForumPublisher;
use App\Digest\Rendering\LearningRenderer;
use App\Digest\Support\TagResolver;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/** One day's tip + topic post: pick topic → write (AI) → check links → render → publish, resumable. */
class LearningPipeline
{
    private const RECENT_TIPS = 60;

    private const MAX_LINKS = 2;

    private const MAX_TAGS = 3;

    public function __construct(
        private readonly TopicPicker $topics,
        private readonly LinkChecker $links,
        private readonly LearningRenderer $renderer,
        private readonly ForumPublisher $publisher,
        private readonly TagResolver $tags,
    ) {}

    public function run(CarbonImmutable $today, bool $dryRun = false): Post
    {
        if ($dryRun) {
            DB::beginTransaction();

            try {
                $post = $this->post($today);
                if ($post->rendered === null) {
                    $this->generate($post, $today);
                }

                return $post;
            } finally {
                DB::rollBack();
            }
        }

        $post = $this->post($today);

        if ($post->status === PostStatus::Posted) {
            return $post;
        }

        try {
            if ($post->rendered === null) {
                $this->generate($post, $today);
            }

            $this->publisher->publish($post, (string) config('services.discord.learning_forum'));
        } catch (Throwable $e) {
            $post->update(['status' => PostStatus::Failed, 'attempts' => $post->attempts + 1, 'last_error' => $e->getMessage()]);

            throw $e;
        }

        return $post->refresh();
    }

    private function post(CarbonImmutable $today): Post
    {
        return Post::query()->firstOrCreate(['date' => $today->toDateString(), 'kind' => PostKind::Learning]);
    }

    private function generate(Post $post, CarbonImmutable $today): void
    {
        $topic = $this->topics->next($today);
        $response = $this->ask($topic);
        $answer = $response->toArray();

        [$tip, $topicPost] = DB::transaction(function () use ($post, $topic, $answer) {
            $post->learningPosts()->delete();

            return [
                $this->storeTip($post, is_array($answer['tip'] ?? null) ? $answer['tip'] : []),
                $this->storeTopic($post, $topic, is_array($answer['topic'] ?? null) ? $answer['topic'] : []),
            ];
        });

        $rendered = $this->renderer->render($today, $tip, $topicPost);

        $post->update([
            'rendered' => $rendered->toArray(),
            'model_used' => "{$response->meta->provider}:{$response->meta->model}",
            'status' => PostStatus::Generated,
        ]);
    }

    private function ask(LearningTopic $topic): StructuredAgentResponse
    {
        $agent = new LearningWriter(
            profile: (string) config('digest.profile'),
            recentTipTitles: array_values(array_map(strval(...), LearningPost::query()
                ->where('kind', LearningKind::Tip)->latest('id')->limit(self::RECENT_TIPS)->pluck('title')->all())),
            existingTags: array_values(array_map(strval(...), Tag::query()->orderBy('slug')->pluck('slug')->all())),
        );

        try {
            $response = $agent->prompt(
                "Today's topic: {$topic->title} (category: {$topic->category}). Write today's tip and the explanation of this topic.",
                provider: ProviderChain::fromConfig(),
                timeout: (int) config('digest.ai_timeout'),
            );
        } catch (Throwable $e) {
            throw new AiUnavailableException($e->getMessage(), previous: $e);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new AiUnavailableException('The AI provider returned no structured output');
        }

        return $response;
    }

    /**
     * @param  array<mixed>  $tip
     */
    private function storeTip(Post $post, array $tip): LearningPost
    {
        $learningPost = $post->learningPosts()->create([
            'kind' => LearningKind::Tip,
            'title' => Str::limit(trim((string) ($tip['title'] ?? '')), 99, '…'),
            'body' => trim((string) ($tip['body'] ?? '')),
            'links' => [],
        ]);

        return $this->tag($learningPost, $tip['tags'] ?? []);
    }

    /**
     * @param  array<mixed>  $topicAnswer
     */
    private function storeTopic(Post $post, LearningTopic $topic, array $topicAnswer): LearningPost
    {
        $body = trim((string) ($topicAnswer['body'] ?? ''));
        $angle = trim((string) ($topicAnswer['laravel_angle'] ?? ''));
        if ($angle !== '') {
            $body .= "\n\n**In Laravel:** {$angle}";
        }

        $learningPost = $post->learningPosts()->create([
            'kind' => LearningKind::Topic,
            'learning_topic_id' => $topic->id,
            'title' => $topic->title,
            'body' => $body,
            'links' => $this->aliveLinks($topicAnswer['links'] ?? []),
        ]);

        return $this->tag($learningPost, $topicAnswer['tags'] ?? []);
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    private function aliveLinks(mixed $links): array
    {
        $alive = [];

        foreach (is_array($links) ? $links : [] as $link) {
            $url = is_array($link) && is_string($link['url'] ?? null) ? trim($link['url']) : '';
            if ($url !== '' && count($alive) < self::MAX_LINKS && $this->links->alive($url)) {
                $alive[] = ['title' => trim((string) ($link['title'] ?? $url)), 'url' => $url];
            }
        }

        return $alive;
    }

    private function tag(LearningPost $learningPost, mixed $tags): LearningPost
    {
        $slugs = array_slice(array_values(array_filter(is_array($tags) ? $tags : [], is_string(...))), 0, self::MAX_TAGS);
        $learningPost->tags()->sync($this->tags->resolve($slugs)->pluck('id'));

        return $learningPost->load('tags');
    }
}
