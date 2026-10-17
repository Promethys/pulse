<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Digest\Sources\SourceCollector;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DigestSources extends Command
{
    protected $signature = 'digest:sources {--since=24h : How far back to look, e.g. 24h or 3d}';

    protected $description = 'Fetch every enabled source without storing anything, and report counts and errors';

    public function handle(SourceCollector $collector): int
    {
        $option = $this->option('since');
        $since = $this->since(is_string($option) ? $option : '');
        $rows = [];
        $failed = false;

        foreach (Source::query()->enabled()->get() as $source) {
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
