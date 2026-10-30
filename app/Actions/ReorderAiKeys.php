<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderAiKeys
{
    /** @param list<int> $ids */
    public function handle(User $user, array $ids): void
    {
        DB::transaction(function () use ($user, $ids): void {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->aiKeys()->pluck('id')->all();
            if (count($existing) !== count($ids) || count(array_unique($ids)) !== count($ids) || array_diff($existing, $ids) !== []) {
                throw ValidationException::withMessages(['ids' => __('Choose all of your keys.')]);
            }
            foreach ($ids as $position => $id) {
                $user->aiKeys()->whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }
}
