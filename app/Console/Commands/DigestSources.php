<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Sources\SourceCollector;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class DigestSources extends Command
{
    protected $signature = 'digest:sources
        {--user= : Email of the user whose enabled subscriptions to check}
        {--since=24h : How far back to look, e.g. 24h or 3d}';

    protected $description = 'Fetch every enabled source without storing anything, and report counts and errors';

    public function handle(SourceCollector $collector): int
    {
        $sources = Source::query()->enabled();
        $email = $this->option('user');

        if ($email !== null) {
            if (! is_string($email) || $email === '') {
                $this->error('The --user option requires an email.');

                return self::FAILURE;
            }

            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->error("User {$email} was not found.");

                return self::FAILURE;
            }

            $sources->whereHas('subscriptions', fn (Builder $query) => $query->where('user_id', $user->id)->where('enabled', true));
        }

        $option = $this->option('since');
        $since = $this->since(is_string($option) ? $option : '');
        $rows = [];
        $failed = false;

        foreach ($sources->get() as $source) {
            try {
                $rows[] = [$source->slug, (string) count($collector->fetch($source, $since)), ''];
            } catch (Throwable $e) {
                $rows[] = [$source->slug, '0', $e->getMessage()];
                $failed = true;
            }
        }

        $this->table(['Source', 'Items', 'Error'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function since(string $option): CarbonImmutable
    {
        if (! preg_match('/^(\d+)([hd])$/', $option, $match)) {
            $this->fail("Invalid --since value \"{$option}\" (expected e.g. 24h or 3d)");
        }

        return $match[2] === 'h'
            ? CarbonImmutable::now()->subHours((int) $match[1])
            : CarbonImmutable::now()->subDays((int) $match[1]);
    }
}
