<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocsPagesTest extends TestCase
{
    public function test_index_contains_sections_and_searchable_headings(): void
    {
        $this->get(route('docs.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docs/Index')
                ->has('sections', 8)
                ->where('searchIndex', fn ($entries) => collect($entries)->contains(fn ($entry) => $entry['title'] === 'Add Pulse to Discord' && count($entry['headings']) > 0)));
    }

    public function test_article_contains_rendered_content_and_navigation(): void
    {
        $this->get(route('docs.show', ['section' => 'delivery', 'article' => 'add-pulse-to-discord']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docs/Show')
                ->where('article.title', 'Add Pulse to Discord')
                ->where('article.sectionTitle', 'Delivery')
                ->where('article.html', fn ($html) => str_contains($html, 'docs-callout-warning'))
                ->has('article.headings')
                ->has('prev')->has('next')
                ->where('editUrl', 'https://github.com/Promethys/pulse/edit/main/resources/docs/delivery/add-pulse-to-discord.md'));
    }

    #[DataProvider('unknownArticles')]
    public function test_unknown_articles_have_a_docs_specific_not_found_page(string $section, string $article): void
    {
        $this->get(route('docs.show', compact('section', 'article')))->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('docs/NotFound'));
    }

    public function test_configured_article_without_a_file_is_not_found(): void
    {
        config(['docs.sections' => [['slug' => 'missing', 'title' => 'Missing', 'articles' => [['slug' => 'missing', 'title' => 'Missing', 'summary' => 'Missing file']]]]]);

        $this->get('/docs/missing/missing')->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('docs/NotFound'));
    }

    #[DataProvider('unsafePaths')]
    public function test_path_traversal_is_rejected(string $path): void
    {
        $this->get($path)->assertNotFound();
    }

    public static function unknownArticles(): array
    {
        return [['unknown', 'what-is-pulse'], ['getting-started', 'unknown']];
    }

    public static function unsafePaths(): array
    {
        return [['/docs/../.env'], ['/docs/%2e%2e/%2e%2e'], ['/docs/delivery/%2e%2e%2f.env']];
    }
}
