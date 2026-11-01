<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use App\Docs\DocsLibrary;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DocsLibraryTest extends TestCase
{
    public function test_rendering_strips_raw_html_and_unsafe_links_but_preserves_https_links(): void
    {
        $rendered = app(DocsLibrary::class)->render("<script>alert('x')</script>\n\nHi <img src=x onerror=alert(1)>\n\n[bad](javascript:alert) [safe](https://example.com)");

        $this->assertStringNotContainsString('<script', $rendered['html']);
        $this->assertStringNotContainsString('<img', $rendered['html']);
        $this->assertStringNotContainsString('javascript:', $rendered['html']);
        $this->assertStringContainsString('href="https://example.com"', $rendered['html']);
        $this->assertStringNotContainsString('target=', $rendered['html']);
    }

    public function test_heading_ids_are_unique_and_heading_text_is_plain(): void
    {
        $rendered = app(DocsLibrary::class)->render("# Title\n\n## Hello *world*\n\n### Hello world\n\n## Hello world-2\n\n## Hello world\n\n## !!!");

        $this->assertSame(['hello-world', 'hello-world-2', 'hello-world-2-2', 'hello-world-3', 'section'], array_column($rendered['headings'], 'id'));
        $this->assertSame('Hello world', $rendered['headings'][0]['text']);
        foreach ($rendered['headings'] as $heading) {
            $this->assertStringContainsString('id="'.$heading['id'].'"', $rendered['html']);
        }
    }

    public function test_callouts_and_tables_render_without_changing_ordinary_quotes_or_code(): void
    {
        $rendered = app(DocsLibrary::class)->render("> [!NOTE]\n> A note.\n\n> [!TIP]\n> A tip.\n\n> [!WARNING]\n> Only forums.\n\n> Ordinary quote\n\n```text\n> [!TIP]\n```\n\n| A | B |\n| --- | --- |\n| One | Two |");

        foreach (['note', 'tip', 'warning'] as $kind) {
            $this->assertStringContainsString('docs-callout-'.$kind, $rendered['html']);
        }
        $this->assertStringContainsString('<table>', $rendered['html']);
        $this->assertStringContainsString('<blockquote>', $rendered['html']);
        $this->assertStringContainsString('&gt; [!TIP]', $rendered['html']);
    }

    public function test_only_configured_slugs_can_be_read(): void
    {
        File::shouldReceive('isFile')->never();
        File::shouldReceive('get')->never();

        $this->assertNull(app(DocsLibrary::class)->find('../delivery', 'add-pulse-to-discord'));
        $this->assertNull(app(DocsLibrary::class)->find('delivery', '../../.env'));
        $this->assertNull(app(DocsLibrary::class)->find('delivery', 'unknown'));
    }

    public function test_neighbors_stop_at_the_ends_of_the_configured_order(): void
    {
        File::shouldReceive('isFile')->andReturnTrue();
        File::shouldReceive('get')->andReturn('## Overview');
        $library = app(DocsLibrary::class);
        $first = $library->find('getting-started', 'what-is-pulse');
        $last = $library->find('help', 'contact-and-bug-reports');

        $this->assertNotNull($first);
        $this->assertNull($first['prev']);
        $this->assertSame('create-your-account', $first['next']['slug']);
        $this->assertNotNull($last);
        $this->assertNull($last['next']);
        $this->assertSame('faq', $last['prev']['slug']);
    }

    #[Group('docs-content')]
    public function test_every_configured_article_has_a_markdown_file(): void
    {
        foreach (app(DocsLibrary::class)->sections() as $section) {
            foreach ($section['articles'] as $article) {
                $this->assertFileExists(resource_path('docs/'.$section['slug'].'/'.$article['slug'].'.md'));
            }
        }
    }
}
