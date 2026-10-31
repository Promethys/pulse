<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Digest\Learning\LearningPipeline;
use App\Digest\Learning\LearningUnavailableException;
use App\Digest\News\NewsPipeline;
use App\Enums\PostKind;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunDigest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public User $user,
        public PostKind $kind,
        public CarbonImmutable $date,
    ) {}

    public function uniqueId(): string
    {
        return $this->user->id.':'.$this->kind->value.':'.$this->date->toDateString();
    }

    public function handle(NewsPipeline $news, LearningPipeline $learning): void
    {
        try {
            $this->kind === PostKind::News
                ? $news->runFor($this->user, $this->date)
                : $learning->runFor($this->user, $this->date);
        } catch (LearningUnavailableException $e) {
            Log::warning($e->getMessage(), ['user_id' => $this->user->id]);
        }
    }
}
