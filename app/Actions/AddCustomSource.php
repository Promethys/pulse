<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AddCustomSource
{
    /**
     * @param  array{type: string, name: string, url?: string|null, api_key?: string|null, interests: list<string>}  $data
     */
    public function handle(User $user, array $data): Source
    {
        return DB::transaction(function () use ($user, $data): Source {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (Source::query()->where('user_id', $user->id)->count() >= 30) {
                throw ValidationException::withMessages([$data['type'] === 'feed' ? 'url' : 'name' => __('You may save up to 30 custom sources.')]);
            }
            do {
                $slug = 'custom-'.$user->id.'-'.Str::slug($data['name']).'-'.Str::random(4);
            } while (Source::query()->where('slug', $slug)->exists());

            $source = new Source([
                'slug' => $slug,
                'name' => $data['name'],
                'type' => SourceType::from($data['type']),
                'url' => $data['type'] === 'feed' ? ($data['url'] ?? null) : null,
                'credentials' => $data['type'] === 'resend_inbox' ? ['api_key' => $data['api_key'] ?? ''] : null,
                'tag' => 'general',
                'max_items' => $data['type'] === 'resend_inbox' ? 20 : 10,
                'position' => ((int) Source::query()->max('position')) + 1,
                'is_default' => false,
                'enabled' => true,
            ]);
            $source->user()->associate($user);
            $source->save();
            $source->interests()->sync(Interest::query()->whereIn('slug', $data['interests'])->pluck('id')->all());
            $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);

            return $source;
        });
    }
}
