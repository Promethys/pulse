<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApproveSourceProposal
{
    /**
     * @param  Collection<int, Interest>  $interests
     */
    public function handle(SourceProposal $proposal, Collection $interests, bool $isDefault): Source
    {
        return DB::transaction(function () use ($proposal, $interests, $isDefault): Source {
            $proposal = $proposal->newQuery()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($proposal->status !== SourceProposalStatus::Pending || $proposal->kind !== SourceProposalKind::NewSource) {
                throw new DomainException('Only pending new source proposals can be approved.');
            }

            $name = $proposal->name ?? Str::after((string) parse_url($proposal->url, PHP_URL_HOST), 'www.');
            $baseSlug = Str::slug(str_replace('.', ' ', $name)) ?: 'source';
            $slug = $baseSlug;
            $suffix = 2;
            while (Source::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $source = Source::query()->create([
                'slug' => $slug,
                'name' => $name,
                'type' => SourceType::Feed,
                'url' => $proposal->url,
                'site_url' => $proposal->site_url,
                'enabled' => true,
                'is_default' => $isDefault,
                'tag' => 'devtools',
                'max_items' => 10,
                'position' => ((int) Source::query()->max('position')) + 1,
            ]);
            $interestIds = $interests->pluck('id')->all();
            $source->interests()->sync($interestIds);

            if ($isDefault) {
                $users = User::query()->whereHas('interests', function (Builder $query) use ($interestIds) {
                    $query->whereIn('interests.id', $interestIds);
                })->get();

                foreach ($users as $user) {
                    $user->sourceSubscriptions()->firstOrCreate(['source_id' => $source->id], ['enabled' => true]);
                }
            }

            $proposal->update(['status' => SourceProposalStatus::Approved, 'source_id' => $source->id]);
            Interest::refreshStatuses();

            return $source;
        });
    }
}
