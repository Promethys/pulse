<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateSubscriptions
{
    /**
     * @param  array<int|string, bool|int|string>  $subscriptions
     */
    public function handle(User $user, array $subscriptions): void
    {
        DB::transaction(function () use ($user, $subscriptions): void {
            foreach ($user->sourceSubscriptions()->get() as $subscription) {
                if (array_key_exists($subscription->source_id, $subscriptions)) {
                    $subscription->update(['enabled' => (bool) $subscriptions[$subscription->source_id]]);
                }
            }
        });
    }
}
