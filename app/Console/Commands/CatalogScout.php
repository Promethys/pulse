<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Jobs\ScoutInterest;
use App\Models\Interest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class CatalogScout extends Command
{
    protected $signature = 'catalog:scout {--interest= : Interest slug, including drafts} {--category= : Scout the draft interests of this category} {--max=5 : Maximum proposals per interest}';

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
        } elseif (is_string($this->option('category'))) {
            $category = InterestCategory::tryFrom($this->option('category'));

            if ($category === null) {
                $this->error("Unknown category: {$this->option('category')}");

                return self::FAILURE;
            }

            $interests->where('category', $category)->where('status', InterestStatus::Draft);
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
