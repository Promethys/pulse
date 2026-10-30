<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddCustomSource;
use App\Actions\ChooseInterests;
use App\Actions\TestSource;
use App\Actions\UpdateCustomSource;
use App\Enums\InterestCategory;
use App\Enums\SourceType;
use App\Http\Requests\CustomSourceRequest;
use App\Http\Requests\Onboarding\ChooseInterestsRequest;
use App\Http\Requests\TestSourceRequest;
use App\Http\Requests\UpdateSourceSubscriptionRequest;
use App\Http\Resources\InterestResource;
use App\Models\Interest;
use App\Models\Source;
use App\Reading\SourcesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SourcesController extends Controller
{
    public function index(Request $request, SourcesQuery $query): Response
    {
        $user = $request->user();
        $catalog = $query->catalog($user);
        $custom = $query->custom($user);
        $sources = collect($catalog)->pluck('sources')->flatten(1)->merge($custom)->unique('id');
        $selected = $user->interests()->pluck('interests.id');
        $order = array_flip(array_map(fn (InterestCategory $category) => $category->value, InterestCategory::cases()));
        $interests = Interest::query()->get()->sortBy(fn (Interest $interest) => [$order[$interest->category->value], $interest->position])
            ->values()->each(fn (Interest $interest) => $interest->setAttribute('selected', $selected->contains($interest->id)));

        return Inertia::render('Sources', [
            'catalog' => $catalog,
            'custom' => $custom,
            'interests' => InterestResource::collection($interests)->resolve($request),
            'categories' => array_map(fn (InterestCategory $category) => ['value' => $category->value, 'label' => $category->label()], InterestCategory::cases()),
            'counts' => ['total' => $sources->count(), 'failing' => $sources->where('health', 'failing')->count()],
        ]);
    }

    public function subscription(UpdateSourceSubscriptionRequest $request, Source $source): RedirectResponse
    {
        $data = $request->validated();
        $subscription = $request->user()->sourceSubscriptions()->where('source_id', $source->id)->firstOrFail();
        $subscription->update($data);

        return back();
    }

    public function interests(ChooseInterestsRequest $request, ChooseInterests $action): RedirectResponse
    {
        $action->handle($request->user(), Interest::query()->live()->whereIn('slug', $request->validated('interests'))->get());

        return back();
    }

    public function store(CustomSourceRequest $request, AddCustomSource $action): RedirectResponse
    {
        /** @var array{type: string, name: string, url?: string|null, api_key?: string|null, interests: list<string>} $data */
        $data = $request->validated();
        $action->handle($request->user(), $data);

        return back();
    }

    public function update(CustomSourceRequest $request, Source $source, UpdateCustomSource $action): RedirectResponse
    {
        Gate::authorize('update', $source);
        /** @var array{name: string, url?: string|null, api_key?: string|null, interests: list<string>} $data */
        $data = $request->validated();
        $action->handle($source, $data);

        return back();
    }

    public function destroy(Request $request, Source $source): RedirectResponse
    {
        Gate::authorize('delete', $source);
        $source->delete();

        return back();
    }

    public function test(TestSourceRequest $request, TestSource $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), SourceType::from($request->string('type')->toString()),
            $request->string('url')->toString(), $request->string('api_key')->toString()));
    }
}
