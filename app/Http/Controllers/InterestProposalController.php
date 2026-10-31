<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SuggestInterest;
use App\Enums\InterestCategory;
use App\Http\Requests\SuggestInterestRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class InterestProposalController extends Controller
{
    public function store(SuggestInterestRequest $request, SuggestInterest $action): RedirectResponse
    {
        $action->handle($request->user(), $request->string('name')->toString(),
            InterestCategory::from($request->string('category')->toString()), $request->string('reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your interest suggestion is waiting for review.')]);

        return back();
    }
}
