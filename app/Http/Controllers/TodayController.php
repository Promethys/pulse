<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryChannelType;
use App\Enums\PostStatus;
use App\Http\Resources\DayResource;
use App\Reading\DayQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TodayController extends Controller
{
    public function __construct(private readonly DayQuery $days) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $settings = $user->digestSettings;
        abort_if($settings === null, 403);

        $today = CarbonImmutable::now($settings->timezone)->toDateString();
        $dates = $this->days->dates($user);
        $todayDay = $this->days->for($user, $today);
        $day = $todayDay;

        if ($day === null && $dates !== []) {
            $day = $this->days->for($user, $dates[0]);
        }

        $newsGenerated = $todayDay !== null && $todayDay->news !== null && $todayDay->news['status'] === PostStatus::Generated;

        return Inertia::render('Today', [
            'day' => $day === null ? null : (new DayResource($day))->resolve($request),
            'is_today' => $day !== null && $day->date === $today,
            'today' => $today,
            'next_digest' => ! $settings->news_enabled || $newsGenerated ? null : [
                'time' => $settings->news_time,
                'timezone' => $settings->timezone,
            ],
            'available_dates' => $dates,
            'setup' => [
                'interests' => $user->interests()->exists(),
                'sources' => $user->sourceSubscriptions()->where('enabled', true)->exists(),
                'ai_key' => $user->aiKeys()->exists() || $user->uses_instance_ai,
                'delivery' => $user->deliveryChannels()->where('enabled', true)->where('type', '!=', DeliveryChannelType::InApp)->exists(),
            ],
        ]);
    }
}
