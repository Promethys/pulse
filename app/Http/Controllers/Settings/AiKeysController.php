<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\ReorderAiKeys;
use App\Actions\SaveAiKey;
use App\Actions\TestAiKey;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\SaveAiKeyRequest;
use App\Http\Requests\Onboarding\TestAiKeyRequest;
use App\Http\Requests\Settings\ReorderAiKeysRequest;
use App\Http\Resources\AiKeyResource;
use App\Models\AiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AiKeysController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $keys = $user->aiKeys()->orderBy('position')->get();

        return Inertia::render('settings/AiKeys', [
            'keys' => AiKeyResource::collection($keys)->resolve($request),
            'providers' => array_map(fn (AiProvider $provider) => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'key_url' => $provider->keyUrl(),
                'suggested_models' => $provider->suggestedModels(),
            ], AiProvider::cases()),
            'uses_instance_ai_notice' => $user->uses_instance_ai && $keys->isEmpty(),
        ]);
    }

    public function store(SaveAiKeyRequest $request, SaveAiKey $action): RedirectResponse
    {
        $action->handle($request->user(), AiProvider::from($request->string('provider')->toString()),
            $request->string('model')->toString(), $request->string('api_key')->toString());

        return back();
    }

    public function order(ReorderAiKeysRequest $request, ReorderAiKeys $action): RedirectResponse
    {
        /** @var array{ids: list<int>} $data */
        $data = $request->validated();
        $action->handle($request->user(), $data['ids']);

        return back();
    }

    public function destroy(Request $request, AiKey $aiKey): RedirectResponse
    {
        Gate::authorize('delete', $aiKey);
        $request->user()->aiKeys()->whereKey($aiKey->id)->delete();

        return back();
    }

    public function test(TestAiKeyRequest $request, TestAiKey $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), AiProvider::from($request->string('provider')->toString()),
            $request->string('model')->toString(), $request->string('api_key')->toString()));
    }

    public function testSaved(Request $request, AiKey $aiKey, TestAiKey $action): JsonResponse
    {
        Gate::authorize('view', $aiKey);

        return response()->json($action->handle($request->user(), $aiKey->provider, $aiKey->model, $aiKey->api_key));
    }
}
