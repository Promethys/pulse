import type { NewsItem, OnboardingInterest } from '@/types';

export const publicNews: NewsItem[] = [
    {
        id: 1,
        title: 'Keep related database work together',
        summary:
            'A transaction keeps a group of database changes consistent when one step fails.',
        position: 1,
        url: 'https://laravel.com/docs/database#database-transactions',
        date: '2026-11-01',
        source: 'Laravel documentation',
        tags: [],
    },
    {
        id: 2,
        title: 'Give each list item a stable identity',
        summary:
            'A unique key helps Vue track items when a list changes order.',
        position: 2,
        url: 'https://vuejs.org/guide/essentials/list.html#maintaining-state-with-key',
        date: '2026-11-01',
        source: 'Vue documentation',
        tags: [],
    },
];

export const publicLearning = [
    {
        kind: 'Tip',
        title: 'Load the relationships you read',
        body: "When listing posts with their authors, eager load the authors with with('author') to avoid a separate query for every post.",
    },
    {
        kind: 'Topic',
        title: 'Idempotency',
        body: 'An idempotent operation has the same effect when repeated. It helps make retries safe, such as when a queued job runs more than once.',
    },
];

export const publicInterests: OnboardingInterest[] = [
    {
        id: 1,
        slug: 'artificial-intelligence',
        name: 'Artificial Intelligence',
        category: 'tech',
        icon: 'brain',
        position: 1,
        has_learning: true,
        status: 'live',
        selected: true,
    },
    {
        id: 2,
        slug: 'web-development',
        name: 'Web Development',
        category: 'tech',
        icon: 'code',
        position: 2,
        has_learning: true,
        status: 'live',
        selected: true,
    },
    {
        id: 3,
        slug: 'food',
        name: 'Food',
        category: 'lifestyle',
        icon: 'utensils',
        position: 3,
        has_learning: false,
        status: 'live',
        selected: false,
    },
];

export const publicProviders = [
    'Gemini',
    'Anthropic',
    'OpenAI',
    'Groq',
    'OpenRouter',
    'Z.AI',
];

export const publicFaq = [
    {
        q: 'What does Pulse cost?',
        a: 'Pulse is free and open source. You bring your own AI key. Some providers have free tiers, and yours may charge beyond them. Check usage and billing with your provider.',
    },
    {
        q: 'Which AI providers can I use?',
        a: 'Gemini, Anthropic, OpenAI, Groq, OpenRouter and Z.AI. Add several keys and order them as a fallback chain if a provider is temporarily unavailable or out of quota.',
    },
    {
        q: 'Where do I read my digest?',
        a: 'Always in the app. You can also receive it on Discord through the bot or a webhook, or by email. Each destination can receive news, learning, or both.',
    },
    {
        q: 'Can I add my own sources?',
        a: 'Yes. Add your own RSS or Atom feeds and newsletters through a dedicated inbox. The catalog also includes GitHub trending. New catalog source proposals are reviewed before they are added.',
    },
    {
        q: 'How are my AI keys stored?',
        a: 'Keys are stored encrypted and shown only as their last four characters. Your profile and selected source content are sent to your chosen AI provider to prepare your digests.',
    },
    {
        q: 'Can I run my own copy?',
        a: 'Yes. Pulse is MIT-licensed. The repository README explains the full Docker setup.',
    },
];
