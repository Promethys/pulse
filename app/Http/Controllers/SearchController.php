<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\PostItemResource;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Reading\SearchQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    public function __construct(private readonly SearchQuery $search) {}

    public function __invoke(SearchRequest $request): Response|RedirectResponse
    {
        $user = $request->user();
        $filters = $request->filters();
        $results = $this->search->for($user, $filters)->withQueryString();

        if ($results->currentPage() > $results->lastPage()) {
            return to_route('search.index', [...$request->query(), 'page' => $results->lastPage()]);
        }

        $options = $this->search->options($user);

        return Inertia::render('Search', [
            'results' => array_map(fn (PostItem $item) => (new PostItemResource($item))->resolve($request), array_values($results->items())),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
                'prev_url' => $results->previousPageUrl(),
                'next_url' => $results->nextPageUrl(),
            ],
            'filters' => [
                'q' => $filters->q,
                'tags' => $filters->tags,
                'source' => $filters->source,
                'from' => $filters->from,
                'to' => $filters->to,
            ],
            'options' => [
                'tags' => array_map(fn (Tag $tag) => ['slug' => $tag->slug, 'name' => $tag->name], $options['tags']),
                'sources' => array_map(fn (Source $source) => ['slug' => $source->slug, 'name' => $source->name], $options['sources']),
            ],
        ]);
    }
}
