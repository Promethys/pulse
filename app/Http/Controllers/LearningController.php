<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LearningKind;
use App\Http\Requests\LearningRequest;
use App\Http\Resources\LearningPostResource;
use App\Models\LearningPost;
use App\Models\Tag;
use App\Reading\LearningQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LearningController extends Controller
{
    public function __construct(private readonly LearningQuery $learning) {}

    public function index(LearningRequest $request): Response|RedirectResponse
    {
        $user = $request->user();
        $kind = $request->enum('kind', LearningKind::class);
        $category = $request->validated('category');
        $tag = $request->validated('tag');
        $entries = $this->learning->list($user, $kind, $category, $tag)->withQueryString();

        if ($entries->currentPage() > $entries->lastPage()) {
            return to_route('learning.index', [...$request->query(), 'page' => $entries->lastPage()]);
        }

        $options = $this->learning->filters($user);

        return Inertia::render('learning/Index', [
            'entries' => array_map(fn (LearningPost $entry) => (new LearningPostResource($entry))->resolve($request), array_values($entries->items())),
            'filters' => ['kind' => $kind?->value, 'category' => $category, 'tag' => $tag],
            'options' => [
                'categories' => $options['categories'],
                'tags' => array_map(fn (Tag $tag) => ['slug' => $tag->slug, 'name' => $tag->name], $options['tags']),
            ],
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'prev_url' => $entries->previousPageUrl(),
                'next_url' => $entries->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $entry = $this->learning->find($request->user(), $id);
        abort_if($entry === null, 404);

        return Inertia::render('learning/Show', [
            'entry' => (new LearningPostResource($entry))->resolve($request),
        ]);
    }
}
