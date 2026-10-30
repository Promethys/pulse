<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PostItemResource;
use App\Http\Resources\TagResource;
use App\Models\PostItem;
use App\Models\Tag;
use App\Reading\TagQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TagController extends Controller
{
    public function __construct(private readonly TagQuery $tags) {}

    public function __invoke(Request $request, Tag $tag): Response|RedirectResponse
    {
        $user = $request->user();
        $settings = $user->digestSettings;
        abort_if($settings === null, 403);

        $result = $this->tags->for($user, $tag, CarbonImmutable::now($settings->timezone)->startOfDay(), 30);
        $items = $result['items'];

        if ($items->currentPage() > $items->lastPage()) {
            return to_route('tags.show', ['tag' => $tag->slug, 'page' => $items->lastPage()]);
        }

        return Inertia::render('Tag', [
            'tag' => (new TagResource($result['tag']))->resolve($request),
            'count' => $result['count'],
            'weeks' => $result['weeks'],
            'items' => array_map(fn (PostItem $item) => [
                ...(new PostItemResource($item))->resolve($request),
                'tags' => TagResource::collection($item->tags)->resolve($request),
            ], array_values($items->items())),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'prev_url' => $items->previousPageUrl(),
                'next_url' => $items->nextPageUrl(),
            ],
        ]);
    }
}
