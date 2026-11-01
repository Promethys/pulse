<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class DocsController extends Controller
{
    public function __construct(private readonly DocsLibrary $docs) {}

    public function index(): Response
    {
        return Inertia::render('docs/Index', [
            'sections' => $this->docs->sections(),
            'searchIndex' => $this->docs->searchIndex(),
        ]);
    }

    public function show(Request $request, string $section, string $article): Response|HttpResponse
    {
        $found = $this->docs->find($section, $article);
        if ($found === null) {
            return Inertia::render('docs/NotFound')->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('docs/Show', [
            ...$found,
            'sections' => $this->docs->sections(),
            'editUrl' => 'https://github.com/Promethys/pulse/edit/main/resources/docs/'.$found['article']['section'].'/'.$found['article']['slug'].'.md',
        ]);
    }
}
