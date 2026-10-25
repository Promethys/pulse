<?php

namespace Database\Seeders;

use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceTableSeeder extends Seeder
{
    /** Highest priority first. Idempotent: only creates missing sources, never changes existing rows. */
    private const SOURCES = [
        [
            'anthropic-news',
            'Anthropic News',
            SourceType::Feed,
            'https://raw.githubusercontent.com/Olshansk/rss-feeds/main/feeds/feed_anthropic_news.xml',
            'ai',
            10,
        ],
        [
            'openai-news',
            'OpenAI News',
            SourceType::Feed,
            'https://openai.com/news/rss.xml',
            'ai',
            10,
        ],
        [
            'deepmind',
            'Google DeepMind',
            SourceType::Feed,
            'https://deepmind.google/blog/rss.xml',
            'ai',
            10,
        ],
        [
            'huggingface',
            'Hugging Face',
            SourceType::Feed,
            'https://huggingface.co/blog/feed.xml',
            'ai',
            10,
        ],
        [
            'mcp-blog',
            'MCP Blog',
            SourceType::Feed,
            'https://blog.modelcontextprotocol.io/index.xml',
            'mcp',
            10,
        ],
        [
            'simon-willison',
            'Simon Willison',
            SourceType::Feed,
            'https://simonwillison.net/atom/everything/',
            'ai',
            15,
        ],
        [
            'ainews',
            'AINews (smol.ai)',
            SourceType::Feed,
            'https://news.smol.ai/rss.xml',
            'ai',
            3,
        ],
        [
            'laravel-news',
            'Laravel News',
            SourceType::Feed,
            'https://feed.laravel-news.com/',
            'laravel',
            15,
        ],
        [
            'laravel-blog',
            'Laravel Blog',
            SourceType::Feed,
            'https://laravel.com/feed',
            'laravel',
            10,
        ],
        [
            'freek',
            'freek.dev',
            SourceType::Feed,
            'https://freek.dev/feed',
            'laravel',
            10,
        ],
        [
            'stitcher',
            'stitcher.io',
            SourceType::Feed,
            'https://stitcher.io/rss',
            'php',
            10,
        ],
        [
            'filament-releases',
            'Filament Releases',
            SourceType::Feed,
            'https://github.com/filamentphp/filament/releases.atom',
            'laravel',
            5,
        ],
        [
            'notion',
            'Notion Releases',
            SourceType::Feed,
            'https://www.notion.com/releases/rss.xml',
            'devtools',
            5,
        ],
        [
            'railway',
            'Railway Blog',
            SourceType::Feed,
            'https://blog.railway.com/rss.xml',
            'devtools',
            5,
        ],
        [
            'kilo',
            'Kilo Blog',
            SourceType::Feed,
            'https://blog.kilo.ai/feed',
            'ai',
            10,
        ],
        [
            'pragmatic-engineer',
            'The Pragmatic Engineer',
            SourceType::Feed,
            'https://newsletter.pragmaticengineer.com/feed',
            'engineering',
            5,
        ],
        [
            'bytebytego',
            'ByteByteGo',
            SourceType::Feed,
            'https://blog.bytebytego.com/feed',
            'engineering',
            5,
        ],
        [
            'martin-fowler',
            'Martin Fowler',
            SourceType::Feed,
            'https://martinfowler.com/feed.atom',
            'engineering',
            5,
        ],
        [
            'hacker-news',
            'Hacker News',
            SourceType::Feed,
            'https://hnrss.org/frontpage?points=200',
            'devtools',
            30,
        ],
        [
            'lobsters',
            'Lobsters',
            SourceType::Feed,
            'https://lobste.rs/rss',
            'engineering',
            15,
        ],
        [
            'laravel-io',
            'Laravel.io Forum',
            SourceType::Feed,
            'https://laravel.io/forum/feed',
            'laravel',
            10,
        ],
        [
            'r-laravel',
            'r/laravel',
            SourceType::Feed,
            'https://www.reddit.com/r/laravel/top/.rss?t=day',
            'laravel',
            10,
        ],
        [
            'r-claudeai',
            'r/ClaudeAI',
            SourceType::Feed,
            'https://www.reddit.com/r/ClaudeAI/top/.rss?t=day',
            'ai',
            10,
        ],
        [
            'r-localllama',
            'r/LocalLLaMA',
            SourceType::Feed,
            'https://www.reddit.com/r/LocalLLaMA/top/.rss?t=day',
            'ai',
            10,
        ],
        [
            'github-trending',
            'GitHub Trending',
            SourceType::GithubTrending,
            'https://github.com/trending?since=daily',
            'devtools',
            10,
        ],
        [
            'newsletters',
            'Newsletters',
            SourceType::ResendInbox,
            null,
            'devtools',
            20,
        ],
    ];

    private const SOURCE_INTERESTS = [
        'anthropic-news' => ['artificial-intelligence'],
        'openai-news' => ['artificial-intelligence'],
        'deepmind' => ['artificial-intelligence'],
        'huggingface' => ['artificial-intelligence'],
        'mcp-blog' => ['artificial-intelligence', 'programming'],
        'simon-willison' => ['artificial-intelligence'],
        'ainews' => ['artificial-intelligence'],
        'laravel-news' => ['programming', 'programming-languages'],
        'laravel-blog' => ['programming', 'programming-languages'],
        'freek' => ['programming', 'programming-languages'],
        'stitcher' => ['programming', 'programming-languages'],
        'filament-releases' => ['programming', 'programming-languages'],
        'notion' => ['productivity'],
        'railway' => ['devops'],
        'kilo' => ['artificial-intelligence'],
        'pragmatic-engineer' => ['programming'],
        'bytebytego' => ['programming'],
        'martin-fowler' => ['programming'],
        'hacker-news' => ['programming', 'tech-companies'],
        'lobsters' => ['programming'],
        'laravel-io' => ['programming', 'programming-languages'],
        'r-laravel' => ['programming', 'programming-languages'],
        'r-claudeai' => ['artificial-intelligence'],
        'r-localllama' => ['artificial-intelligence'],
        'github-trending' => ['programming'],
        'newsletters' => [],
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $position => [$slug, $name, $type, $url, $tag, $maxItems]) {
            Source::query()->firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
                'type' => $type,
                'url' => $url,
                'tag' => $tag,
                'max_items' => $maxItems,
                'position' => $position + 1,
                'is_default' => $slug !== 'newsletters',
            ]);
        }

        $interests = Interest::query()->pluck('id', 'slug');
        $sources = Source::query()->whereIn('slug', array_keys(self::SOURCE_INTERESTS))->doesntHave('interests')->get();

        foreach ($sources as $source) {
            $interestIds = $interests->only(self::SOURCE_INTERESTS[$source->slug])->values()->all();
            $source->interests()->sync($interestIds);
        }

        Interest::refreshStatuses();
    }
}
