<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScoutInterest;
use App\Models\Interest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class CatalogScout extends Command
{
    protected $signature = 'catalog:scout {--interest= : Interest slug, including drafts} {--max=5 : Maximum proposals per interest}';

    protected $description = 'Dispatch AI feed scouts for catalog interests';

    public function handle(): int
    {
        $slug = $this->option('interest');
        $interests = Interest::query();

        if (is_string($slug)) {
            $interests->where('slug', $slug);

            if (! $interests->exists()) {
                $this->error("Unknown interest: {$slug}");

                return self::FAILURE;
            }
        } else {
            $interests->live();
        }

        $dispatched = 0;

        foreach ($interests->get() as $interest) {
            Bus::dispatch(new ScoutInterest($interest, (int) $this->option('max')));
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} scouts");

        return self::SUCCESS;
    }
}
