<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Digest\Sources\SourceCollector;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CollectSources implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(SourceCollector $collector): void
    {
        $collector->collectFor(collect([$this->user]));
    }
}
