<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Learning\LearningPipeline;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DigestLearning extends Command
{
    protected $signature = 'digest:learning
        {--dry-run : Build today\'s tip and topic and print them, without storing or posting anything}
        {--force : Post today\'s tip and topic again (same topic), even if already posted}';

    protected $description = 'Build and post today\'s tip and topic of the day';

    public function handle(LearningPipeline $pipeline): int
    {
        $today = CarbonImmutable::now((string) config('digest.timezone'))->startOfDay();

        if ($this->option('force') && ! $this->option('dry-run')) {
            $this->reset($today);
        }

        try {
            $post = $pipeline->run($today, dryRun: (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line((string) ($post->rendered['title'] ?? ''));
            foreach ($post->rendered['messages'] ?? [] as $message) {
                $this->line('---');
                $this->line((string) $message);
            }

            return self::SUCCESS;
        }

        $this->info("Learning post {$post->status->value} (thread {$post->discord_thread_id}).");

        return self::SUCCESS;
    }

    /** Back to a fresh post; today's topic stays picked, so the new run writes about the same topic. */
    private function reset(CarbonImmutable $today): void
    {
        $post = Post::query()->where('date', $today->toDateString())->where('kind', PostKind::Learning)->first();

        if ($post === null) {
            return;
        }

        DB::transaction(function () use ($post) {
            $post->learningPosts()->delete();
            $post->update(['status' => PostStatus::Pending, 'rendered' => null, 'discord_thread_id' => null, 'messages_sent' => 0, 'attempts' => 0, 'last_error' => null, 'posted_at' => null, 'model_used' => null]);
        });
    }
}
