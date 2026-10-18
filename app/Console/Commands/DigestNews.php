<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\News\NewsPipeline;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DigestNews extends Command
{
    protected $signature = 'digest:news
        {--dry-run : Build today\'s post and print it, without storing or posting anything}
        {--force : Post today\'s news again, even if it was already posted}';

    protected $description = 'Build and post today\'s news digest';

    public function handle(NewsPipeline $pipeline): int
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

        $this->info("News post {$post->status->value} (thread {$post->discord_thread_id}).");

        return self::SUCCESS;
    }

    /** Back to a fresh post; its selected items become candidates again so the new run can pick them. */
    private function reset(CarbonImmutable $today): void
    {
        $post = Post::query()->where('date', $today->toDateString())->where('kind', PostKind::News)->first();

        if ($post === null) {
            return;
        }

        DB::transaction(function () use ($post) {
            $post->items()->each(function ($item) {
                $item->tags()->detach();
                $item->update(['status' => ItemStatus::Candidate, 'post_id' => null, 'summary' => null]);
            });
            $post->update(['status' => PostStatus::Pending, 'rendered' => null, 'discord_thread_id' => null, 'messages_sent' => 0, 'attempts' => 0, 'last_error' => null, 'posted_at' => null, 'model_used' => null]);
        });
    }
}
