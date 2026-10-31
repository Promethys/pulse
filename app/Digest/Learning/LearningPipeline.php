<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Ai\Agents\LearningWriter;
use App\Digest\Ai\AiChainResolver;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\Rendering\LearningRenderer;
use App\Digest\Support\TagResolver;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
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
        private readonly TagResolver $tags,
        private readonly PostDeliverer $deliverer,
        private readonly AiChainResolver $ai,
    ) {}

    public function runFor(User $user, CarbonImmutable $today, bool $dryRun = false): Post
    {
        if (! $user->learningAvailable()) {
            throw new LearningUnavailableException('Learning is unavailable for this user.');
        }

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $post = $this->post($today, $user);

            if ($post->rendered === null) {
                try {
                    $this->generate($post, $today, $user);
                } catch (Throwable $e) {
                    $post->update(['status' => PostStatus::Failed, 'attempts' => $post->attempts + 1, 'last_error' => $e->getMessage()]);

                    if ($post->attempts >= (int) config('digest.max_attempts')) {
                        $user->learningTopicUsages()->whereDate('used_on', $today->toDateString())->delete();
                    }

                    throw $e;
                }
            }

            if (! $dryRun) {
                $this->deliverer->deliver($post);
            }

            return $post;
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }
    }

    private function post(CarbonImmutable $today, User $user): Post
    {
        return Post::query()->firstOrCreate(['user_id' => $user->id, 'date' => $today->toDateString(), 'kind' => PostKind::Learning]);
    }

    private function generate(Post $post, CarbonImmutable $today, User $user): void
    {
        $topic = $this->topics->nextFor($user, $today);
        $response = $this->ask($topic, $user);
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

    private function ask(LearningTopic $topic, User $user): StructuredAgentResponse
    {
        $recentTips = LearningPost::query()->where('learning_posts.kind', LearningKind::Tip);
        $recentTips->join('posts', 'posts.id', '=', 'learning_posts.post_id')->where('posts.user_id', $user->id);

        $agent = new LearningWriter(
            profile: (string) $user->digestSettings?->profile,
            recentTipTitles: array_values(array_map(strval(...), $recentTips
                ->latest('learning_posts.id')->limit(self::RECENT_TIPS)->pluck('learning_posts.title')->all())),
            existingTags: array_values(array_map(strval(...), Tag::query()->orderBy('slug')->pluck('slug')->all())),
        );

        try {
            $response = $agent->prompt(
                "Today's topic: {$topic->title} (category: {$topic->category}). Write today's tip and the explanation of this topic.",
                provider: $this->ai->for($user),
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
        $angle = trim((string) ($topicAnswer['stack_angle'] ?? ''));
        if ($angle !== '') {
            $body .= "\n\n**In practice:** {$angle}";
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
