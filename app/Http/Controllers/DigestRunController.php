<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RunDigestNow;
use App\Enums\PostKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DigestRunController extends Controller
{
    public function __invoke(Request $request, RunDigestNow $action): RedirectResponse
    {
        $validated = $request->validate(['kind' => ['required', Rule::enum(PostKind::class)]]);
        $action->handle($request->user(), PostKind::from($validated['kind']));

        return back();
    }
}
