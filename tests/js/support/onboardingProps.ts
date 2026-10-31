export const onboardingSteps = [
    'Interests',
    'Sources',
    'About you',
    'AI key',
    'Delivery',
];

export function onboardingProps() {
    return {
        interests: [
            {
                id: 1,
                slug: 'artificial-intelligence',
                name: 'Artificial intelligence',
                category: 'technology',
                icon: 'brain',
                position: 1,
                has_learning: true,
                status: 'live',
                selected: true,
            },
            {
                id: 2,
                slug: 'security',
                name: 'Security',
                category: 'technology',
                icon: 'shield',
                position: 2,
                has_learning: true,
                status: 'live',
                selected: false,
            },
            {
                id: 3,
                slug: 'robotics',
                name: 'Robotics',
                category: 'technology',
                icon: 'wrench',
                position: 3,
                has_learning: false,
                status: 'draft',
                selected: false,
            },
            {
                id: 4,
                slug: 'programming',
                name: 'Programming',
                category: 'software_development',
                icon: 'code',
                position: 1,
                has_learning: true,
                status: 'live',
                selected: true,
            },
        ],
        categories: [
            { value: 'technology', label: 'Technology' },
            { value: 'software_development', label: 'Software development' },
        ],
        sources: [
            {
                interest: {
                    slug: 'artificial-intelligence',
                    name: 'Artificial intelligence',
                },
                sources: [
                    {
                        id: 11,
                        slug: 'ai-news',
                        name: 'AI News with a deliberately long source name',
                        type: 'feed',
                        url: 'https://ai.example.test/feed',
                        site_url: 'https://ai.example.test',
                        is_custom: false,
                        is_default: true,
                        enabled: true,
                        last_fetched_at: null,
                        last_success_at: null,
                        last_error: null,
                        health: 'unknown',
                        retired: false,
                        subscription_enabled: true,
                    },
                    {
                        id: 12,
                        slug: 'research',
                        name: 'Research',
                        type: 'feed',
                        url: 'https://research.example.test/feed',
                        site_url: 'https://research.example.test',
                        is_custom: false,
                        is_default: true,
                        enabled: true,
                        last_fetched_at: null,
                        last_success_at: null,
                        last_error: null,
                        health: 'unknown',
                        retired: false,
                        subscription_enabled: false,
                    },
                ],
            },
            {
                interest: { slug: 'programming', name: 'Programming' },
                sources: [
                    {
                        id: 13,
                        slug: 'github-trending',
                        name: 'GitHub Trending',
                        type: 'github_trending',
                        url: 'https://github.com/trending',
                        site_url: 'https://github.com',
                        is_custom: false,
                        is_default: true,
                        enabled: true,
                        last_fetched_at: null,
                        last_success_at: null,
                        last_error: null,
                        health: 'unknown',
                        retired: false,
                        subscription_enabled: true,
                    },
                ],
            },
        ],
        settings: {
            timezone: 'Europe/Paris',
            profile: 'A curious reader' as string | null,
            news_enabled: true,
            news_time: '09:00',
            learning_enabled: true,
            learning_time: '09:30',
            max_items: 20,
            onboarded_at: null,
            learning_available: true,
        },
        timezones: ['UTC', 'Europe/Paris', 'America/New_York'],
        providers: [
            {
                value: 'gemini',
                label: 'Gemini',
                key_url: 'https://aistudio.google.com/apikey',
                suggested_models: ['gemini-3.8-flash'],
            },
            {
                value: 'anthropic',
                label: 'Anthropic',
                key_url: 'https://console.anthropic.com/settings/keys',
                suggested_models: ['claude-haiku-5-5', 'claude-sonnet-5-5'],
            },
            {
                value: 'openai',
                label: 'OpenAI',
                key_url: 'https://platform.openai.com/api-keys',
                suggested_models: ['gpt-5-mini'],
            },
            {
                value: 'groq',
                label: 'Groq',
                key_url: 'https://console.groq.com/keys',
                suggested_models: ['llama-3.3-70b-versatile'],
            },
            {
                value: 'openrouter',
                label: 'OpenRouter',
                key_url: 'https://openrouter.ai/keys',
                suggested_models: ['deepseek/deepseek-chat'],
            },
            {
                value: 'zai',
                label: 'Z.AI',
                key_url: 'https://z.ai/manage-apikey/apikey-list',
                suggested_models: ['glm-4.7-flash'],
            },
        ],
        ai_keys: [] as {
            id: number;
            provider: string;
            model: string;
            key_suffix: string;
            position: number;
            consented_at: string;
            created_at: string | null;
        }[],
        email: 'reader@example.test',
        email_delivery: false,
        uses_instance_ai: false,
    };
}
