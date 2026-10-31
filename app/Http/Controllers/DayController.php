<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DayResource;
use App\Reading\DayQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DayController extends Controller
{
    public function __construct(private readonly DayQuery $days) {}

    public function __invoke(Request $request, string $date): Response|RedirectResponse
    {
        abort_unless(preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts) === 1, 404);
        abort_unless(checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]), 404);

        $user = $request->user();
        $settings = $user->digestSettings;
        abort_if($settings === null, 403);

        if ($date === CarbonImmutable::now($settings->timezone)->toDateString()) {
            return to_route('home');
        }

        $day = $this->days->for($user, $date);
        abort_if($day === null, 404);

        return Inertia::render('Day', [
            'day' => (new DayResource($day))->resolve($request),
            'available_dates' => $this->days->dates($user),
        ]);
    }
}
