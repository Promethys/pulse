<?php

declare(strict_types=1);

namespace App\Docs;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * @phpstan-type Article array{slug: string, title: string, summary: string}
 * @phpstan-type Section array{slug: string, title: string, articles: list<Article>}
 * @phpstan-type Entry array{slug: string, title: string, summary: string, section: string, sectionTitle: string, url: string}
 * @phpstan-type HeadingEntry array{id: string, text: string, level: int}
 * @phpstan-type IndexEntry array{slug: string, title: string, summary: string, section: string, sectionTitle: string, url: string, headings: list<HeadingEntry>}
 * @phpstan-type Page array{slug: string, title: string, summary: string, section: string, sectionTitle: string, url: string, html: string, headings: list<HeadingEntry>}
 */
final class DocsLibrary
{
    /** @return list<Section> */
    public function sections(): array
    {
        return config('docs.sections', []);
    }

    /** @return list<Entry> */
    private function entries(): array
    {
        $entries = [];
        foreach ($this->sections() as $section) {
            foreach ($section['articles'] as $article) {
                $entries[] = [...$article, 'section' => $section['slug'], 'sectionTitle' => $section['title'], 'url' => route('docs.show', ['section' => $section['slug'], 'article' => $article['slug']], false)];
            }
        }

        return $entries;
    }

    /** @return array{article: Page, prev: Entry|null, next: Entry|null}|null */
    public function find(string $section, string $article): ?array
    {
        $entries = $this->entries();
        foreach ($entries as $index => $entry) {
            if ($entry['section'] !== $section || $entry['slug'] !== $article) {
                continue;
            }

            $path = resource_path('docs/'.$entry['section'].'/'.$entry['slug'].'.md');
            if (! File::isFile($path)) {
                return null;
            }

            return [
                'article' => [...$entry, ...$this->render(File::get($path))],
                'prev' => $entries[$index - 1] ?? null,
                'next' => $entries[$index + 1] ?? null,
            ];
        }

        return null;
    }

    /** @return list<IndexEntry> */
    public function searchIndex(): array
    {
        $index = [];
        foreach ($this->entries() as $entry) {
            $found = $this->find($entry['section'], $entry['slug']);
            if ($found !== null) {
                $index[] = [...$entry, 'headings' => $found['article']['headings']];
            }
        }

        return $index;
    }

    /** @return array{html: string, headings: list<HeadingEntry>} */
    public function render(string $markdown): array
    {
        $headings = [];
        $environment = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use (&$headings): void {
            $used = [];
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Heading && in_array($node->getLevel(), [2, 3], true)) {
                    $text = $this->text($node);
                    $base = Str::slug($text) ?: 'section';
                    $id = $base;
                    $suffix = 2;
                    while (isset($used[$id])) {
                        $id = $base.'-'.$suffix++;
                    }
                    $used[$id] = true;
                    $node->data->set('attributes/id', $id);
                    $node->data->set('attributes/class', 'scroll-mt-24');
                    $headings[] = ['id' => $id, 'text' => $text, 'level' => $node->getLevel()];
                }
                if ($node instanceof BlockQuote) {
                    $first = $node->firstChild()?->firstChild();
                    if ($first instanceof Text && preg_match('/^\[!(NOTE|TIP|WARNING)\](?:\s|$)/', $first->getLiteral(), $match) === 1) {
                        $kind = strtolower($match[1]);
                        $first->setLiteral(ltrim(substr($first->getLiteral(), strlen($match[0]))));
                        $node->data->set('attributes/class', 'docs-callout docs-callout-'.$kind);
                        $label = new Paragraph;
                        $label->data->set('attributes/class', 'docs-callout-title');
                        $label->appendChild(new Text(ucfirst($kind)));
                        $node->prependChild($label);
                    }
                }
            }
        });
        $html = (new MarkdownConverter($environment))->convert($markdown)->getContent();

        return ['html' => $this->themeShots($html), 'headings' => $headings];
    }

    private function themeShots(string $html): string
    {
        return (string) preg_replace(
            '#<img src="(/images/shots/[a-z0-9-]+)\.webp" alt="([^"]*)" />#',
            '<img src="$1.webp" alt="$2" loading="lazy" class="shot-light" /><img src="$1-dark.webp" alt="$2" loading="lazy" class="shot-dark" />',
            $html,
        );
    }

    private function text(Node $node): string
    {
        $text = '';
        foreach ($node->iterator() as $child) {
            if ($child instanceof Text || $child instanceof Code) {
                $text .= $child->getLiteral();
            }
        }

        return $text;
    }
}
