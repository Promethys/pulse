<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\UpdateDigestSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDigestSettingsRequest;
use App\Http\Resources\DigestSettingsResource;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DigestController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Digest', [
            'settings' => (new DigestSettingsResource($request->user()->digestSettings()->sole()))->resolve($request),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateDigestSettingsRequest $request, UpdateDigestSettings $action): RedirectResponse
    {
        /** @var array{profile?: string|null, timezone: string, news_enabled: bool, news_time: string, learning_enabled: bool, learning_time: string, max_items: int} $data */
        $data = $request->validated();
        $action->handle($request->user(), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Digest settings saved.')]);

        return back();
    }
}
