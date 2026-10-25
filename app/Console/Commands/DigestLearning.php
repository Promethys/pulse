<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Learning\LearningPipeline;
use App\Enums\PostKind;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DigestLearning extends Command
{
    protected $signature = 'digest:learning
        {--user= : Email of the user whose digest to build}
        {--dry-run : Build today\'s tip and topic and print them, without storing or posting anything}
        {--force : Post today\'s tip and topic again (same topic), even if already posted}';

    protected $description = 'Build and post today\'s tip and topic of the day';

    public function handle(LearningPipeline $pipeline): int
    {
        $email = $this->option('user');

        if (! is_string($email) || $email === '') {
            $this->error('The --user option is required.');

            return self::FAILURE;
        }

        $user = User::query()->with('digestSettings')->where('email', $email)->first();

        if ($user === null) {
            $this->error("User {$email} was not found.");

            return self::FAILURE;
        }

        $today = CarbonImmutable::now($user->digestSettings->timezone ?? (string) config('digest.timezone'))->startOfDay();

        if ($this->option('force') && ! $this->option('dry-run')) {
            $user->posts()->where('date', $today->toDateString())->where('kind', PostKind::Learning)->delete();
        }

        try {
            $post = $pipeline->runFor($user, $today, dryRun: (bool) $this->option('dry-run'));
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

        $this->info("Learning post {$post->status->value}.");

        return self::SUCCESS;
    }
}
