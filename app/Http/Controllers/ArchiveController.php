<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Reading\ArchiveQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ArchiveController extends Controller
{
    public function __construct(private readonly ArchiveQuery $archive) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $settings = $user->digestSettings;
        abort_if($settings === null, 403);

        $days = $this->archive->for($user, 30);

        if ($days->currentPage() > $days->lastPage()) {
            return to_route('archive.index', ['page' => $days->lastPage()]);
        }

        return Inertia::render('Archive', [
            'days' => array_values($days->through(fn (array $day) => [
                'date' => $day['date'],
                'item_count' => $day['item_count'],
                'tags' => TagResource::collection($day['tags'])->resolve($request),
                'news_status' => $day['news_status']?->value,
                'learning_status' => $day['learning_status']?->value,
            ])->items()),
            'pagination' => [
                'current_page' => $days->currentPage(),
                'last_page' => $days->lastPage(),
                'total' => $days->total(),
                'prev_url' => $days->previousPageUrl(),
                'next_url' => $days->nextPageUrl(),
            ],
            'today' => CarbonImmutable::now($settings->timezone)->toDateString(),
        ]);
    }
}
