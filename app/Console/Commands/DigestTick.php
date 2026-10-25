<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Delivery\PostDeliverer;
use App\Digest\DueDigest;
use App\Digest\Sources\SourceCollector;
use App\Digest\TickScheduler;
use App\Enums\DeliveryStatus;
use App\Enums\ItemStatus;
use App\Jobs\RunDigest;
use App\Models\Item;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class DigestTick extends Command
{
    private const PRUNE_SKIPPED_AFTER_DAYS = 30;

    protected $signature = 'digest:tick';

    protected $description = 'Entry point of the cron: run whatever is due now (news, learning), then prune old skipped items';

    public function handle(TickScheduler $scheduler, SourceCollector $collector, PostDeliverer $deliverer): int
    {
        $now = CarbonImmutable::now('UTC');
        $due = $scheduler->due($now);

        if ($due !== []) {
            $users = collect($due)->map(fn (DueDigest $digest) => $digest->user)->unique('id')->values();
            $collector->collectFor($users);
        }

        foreach ($due as $digest) {
            RunDigest::dispatch($digest->user, $digest->kind, $digest->date);
        }

        $this->info('Dispatched '.count($due).' digests');

        $posts = Post::query()
            ->where('created_at', '>=', $now->subDays(2))
            ->whereHas('deliveries', fn (Builder $query) => $query
                ->where('status', DeliveryStatus::Failed)
                ->where('attempts', '<', (int) config('digest.max_attempts')))
            ->get();
        $failed = 0;

        foreach ($posts as $post) {
            try {
                if ($deliverer->deliver($post) > 0) {
                    $failed++;
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("Post {$post->id} delivery failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Retried deliveries for {$posts->count()} posts");

        // Undated skipped items are kept: they always pass the date filter, so they are the only record they were seen.
        $pruned = Item::query()
            ->where('status', ItemStatus::Skipped)
            ->whereNotNull('published_at')
            ->where('created_at', '<', $now->subDays(self::PRUNE_SKIPPED_AFTER_DAYS))
            ->delete();

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} old skipped items");
        }

        return $due === [] && $posts->isNotEmpty() && $failed === $posts->count() ? self::FAILURE : self::SUCCESS;
    }
}
