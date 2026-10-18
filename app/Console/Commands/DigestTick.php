<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Learning\LearningPipeline;
use App\Digest\News\NewsPipeline;
use App\Digest\TickScheduler;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Models\Item;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DigestTick extends Command
{
    private const PRUNE_SKIPPED_AFTER_DAYS = 30;

    protected $signature = 'digest:tick';

    protected $description = 'Entry point of the cron: run whatever is due now (news, learning), then prune old skipped items';

    public function handle(TickScheduler $scheduler, NewsPipeline $news, LearningPipeline $learning): int
    {
        $now = CarbonImmutable::now('UTC');
        $today = $scheduler->localToday($now);
        $due = $scheduler->due($now);
        $failed = 0;

        foreach ($due as $kind) {
            try {
                $kind === PostKind::News ? $news->run($today) : $learning->run($today);
                $this->info("{$kind->value} posted");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$kind->value} failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $pruned = Item::query()
            ->where('status', ItemStatus::Skipped)
            ->where('created_at', '<', $now->subDays(self::PRUNE_SKIPPED_AFTER_DAYS))
            ->delete();

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} old skipped items");
        }

        return $due !== [] && $failed === count($due) ? self::FAILURE : self::SUCCESS;
    }
}
