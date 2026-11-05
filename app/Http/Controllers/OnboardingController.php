<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ChooseInterests;
use App\Actions\CompleteOnboarding;
use App\Actions\SaveAiKey;
use App\Actions\TestAiKey;
use App\Actions\UpdateDigestProfile;
use App\Actions\UpdateSubscriptions;
use App\Enums\AiProvider;
use App\Enums\DeliveryChannelType;
use App\Enums\InterestCategory;
use App\Http\Requests\Onboarding\ChooseInterestsRequest;
use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Http\Requests\Onboarding\SaveAiKeyRequest;
use App\Http\Requests\Onboarding\TestAiKeyRequest;
use App\Http\Requests\Onboarding\UpdateDigestProfileRequest;
use App\Http\Requests\Onboarding\UpdateSubscriptionsRequest;
use App\Http\Resources\AiKeyResource;
use App\Http\Resources\DigestSettingsResource;
use App\Http\Resources\InterestResource;
use App\Http\Resources\SourceResource;
use App\Models\Interest;
use App\Models\Source;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $settings = $user->digestSettings()->firstOrCreate()->refresh();

        if ($settings->onboarded_at !== null) {
            return to_route('dashboard');
        }

        $selected = $user->interests()->get();
        $categoryOrder = array_flip(array_map(fn (InterestCategory $category) => $category->value, InterestCategory::cases()));
        $interests = Interest::query()->get()->sortBy(fn (Interest $interest) => [$categoryOrder[$interest->category->value], $interest->position])
            ->values()->each(fn (Interest $interest) => $interest->setAttribute('selected', $selected->contains('id', $interest->id)));
        $subscriptions = $user->sourceSubscriptions()->get()->keyBy('source_id');
        $sources = $selected->map(fn (Interest $interest) => [
            'interest' => ['slug' => $interest->slug, 'name' => $interest->name],
            'sources' => $interest->sources()->catalog()->where('is_default', true)
                ->whereIn('sources.id', $subscriptions->keys())->orderBy('name')->get()
                ->map(fn (Source $source) => [
                    ...(new SourceResource($source))->resolve($request),
                    'subscription_enabled' => $subscriptions->get($source->id)->enabled ?? false,
                ])->all(),
        ])->all();
        $email = $user->deliveryChannels()->where('type', DeliveryChannelType::Email)->where('enabled', true)->first();

        return Inertia::render('Onboarding', [
            'interests' => InterestResource::collection($interests)->resolve($request),
            'categories' => array_map(fn (InterestCategory $category) => ['value' => $category->value, 'label' => $category->label()], InterestCategory::cases()),
            'sources' => $sources,
            'settings' => (new DigestSettingsResource($settings))->resolve($request),
            'timezones' => DateTimeZone::listIdentifiers(),
            'providers' => array_map(fn (AiProvider $provider) => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'key_url' => $provider->keyUrl(),
                'suggested_models' => $provider->suggestedModels(),
            ], AiProvider::cases()),
            'ai_keys' => AiKeyResource::collection($user->aiKeys()->get())->resolve($request),
            'email' => $email?->settings['address'] ?? $user->email,
            'email_delivery' => $email !== null,
            'uses_instance_ai' => (bool) $user->uses_instance_ai,
        ]);
    }

    public function interests(ChooseInterestsRequest $request, ChooseInterests $action): RedirectResponse
    {
        $action->handle($request->user(), Interest::query()->live()->whereIn('slug', $request->validated('interests'))->get());

        return back();
    }

    public function sources(UpdateSubscriptionsRequest $request, UpdateSubscriptions $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('subscriptions'));

        return back();
    }

    public function about(UpdateDigestProfileRequest $request, UpdateDigestProfile $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('profile'), $request->string('timezone')->toString());

        return back();
    }

    public function aiKey(SaveAiKeyRequest $request, SaveAiKey $action): RedirectResponse
    {
        $action->handle($request->user(), AiProvider::from($request->string('provider')->toString()),
            $request->string('model')->toString(), $request->string('api_key')->toString());

        return back();
    }

    public function testAiKey(TestAiKeyRequest $request, TestAiKey $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), AiProvider::from($request->string('provider')->toString()),
            $request->string('model')->toString(), $request->string('api_key')->toString()));
    }

    public function complete(CompleteOnboardingRequest $request, CompleteOnboarding $action): RedirectResponse
    {
        $action->handle($request->user(), $request->boolean('email_delivery'), $request->validated('email'));

        return to_route('dashboard');
    }
}
