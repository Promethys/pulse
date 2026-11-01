<?php

declare(strict_types=1);

return [
    'sections' => [
        [
            'slug' => 'getting-started',
            'title' => 'Getting started',
            'articles' => [
                ['slug' => 'what-is-pulse', 'title' => 'What is Pulse', 'summary' => 'Daily news, plus tips and topics when learning is available.'],
                ['slug' => 'create-your-account', 'title' => 'Create your account', 'summary' => 'Sign up, choose a password, confirm your email.'],
                ['slug' => 'your-first-digest', 'title' => 'Your first digest', 'summary' => 'What happens between sign-up and the first morning.'],
                ['slug' => 'when-digests-arrive', 'title' => 'When digests arrive', 'summary' => 'Default times, your timezone, and how to change them.'],
            ],
        ],
        [
            'slug' => 'interests-and-sources',
            'title' => 'Interests and sources',
            'articles' => [
                ['slug' => 'choose-your-interests', 'title' => 'Choose your interests', 'summary' => '50+ interests, with available choices backed by catalog sources.'],
                ['slug' => 'manage-sources', 'title' => 'Manage sources', 'summary' => 'Switch sources off and watch their health.'],
                ['slug' => 'add-your-own-feed', 'title' => 'Add your own feed', 'summary' => 'Add a publicly accessible RSS or Atom feed.'],
                ['slug' => 'follow-newsletters', 'title' => 'Follow newsletters', 'summary' => 'Connect your own Resend receiving inbox.'],
                ['slug' => 'suggest-a-source-or-interest', 'title' => 'Suggest a source or interest', 'summary' => 'Proposals are reviewed by a person.'],
            ],
        ],
        [
            'slug' => 'ai-keys',
            'title' => 'AI keys',
            'articles' => [
                ['slug' => 'why-you-bring-your-own-key', 'title' => 'Why you bring your own key', 'summary' => 'Use your provider account to generate your digests.'],
                ['slug' => 'what-it-costs', 'title' => 'What it costs', 'summary' => 'How AI provider usage and billing work.'],
                ['slug' => 'get-a-free-key', 'title' => 'Get a free key', 'summary' => 'Supported providers and their key pages; free access varies.'],
                ['slug' => 'add-and-order-your-keys', 'title' => 'Add and order your keys', 'summary' => 'Several keys form a fallback chain.'],
                ['slug' => 'troubleshoot-a-failing-key', 'title' => 'Troubleshoot a failing key', 'summary' => 'Quota, revoked keys and wrong models.'],
            ],
        ],
        [
            'slug' => 'your-digest',
            'title' => 'Your digest',
            'articles' => [
                ['slug' => 'news', 'title' => 'News', 'summary' => 'Stories grouped by tag, with short summaries and original links.'],
                ['slug' => 'tips-and-topics', 'title' => 'Tips and topics', 'summary' => 'The second post tech interests get.'],
                ['slug' => 'write-a-good-profile', 'title' => 'Write a good profile', 'summary' => 'Two sentences that steer your digest.'],
                ['slug' => 'change-times-and-limits', 'title' => 'Change times and limits', 'summary' => 'Preparation times, news item limits and pause switches.'],
            ],
        ],
        [
            'slug' => 'delivery',
            'title' => 'Delivery',
            'articles' => [
                ['slug' => 'in-the-app', 'title' => 'In the app', 'summary' => 'The digest is always here; nothing to set up.'],
                ['slug' => 'add-pulse-to-discord', 'title' => 'Add Pulse to Discord', 'summary' => 'Post your digest to a forum channel with the Pulse bot.'],
                ['slug' => 'discord-webhooks', 'title' => 'Discord webhooks', 'summary' => 'No bot access? A webhook works too.'],
                ['slug' => 'email', 'title' => 'Email', 'summary' => 'The digest in your inbox: news, learning, or both.'],
            ],
        ],
        [
            'slug' => 'reading',
            'title' => 'Reading',
            'articles' => [
                ['slug' => 'today-and-past-days', 'title' => 'Today and past days', 'summary' => 'The day view and its calendar.'],
                ['slug' => 'archive', 'title' => 'Archive', 'summary' => 'Every digest you have received.'],
                ['slug' => 'search', 'title' => 'Search', 'summary' => 'Search your digests and filter the results.'],
                ['slug' => 'tags', 'title' => 'Tags', 'summary' => 'Tag pages with a 12-week chart.'],
                ['slug' => 'learning', 'title' => 'Learning', 'summary' => 'Every tip and topic, kept.'],
            ],
        ],
        [
            'slug' => 'account',
            'title' => 'Account',
            'articles' => [
                ['slug' => 'profile-and-appearance', 'title' => 'Profile and appearance', 'summary' => 'Your name, your profile text, dark mode.'],
                ['slug' => 'two-factor-and-passkeys', 'title' => 'Two-factor and passkeys', 'summary' => 'Add a second factor or use a passkey for passwordless sign-in.'],
                ['slug' => 'delete-your-account', 'title' => 'Delete your account', 'summary' => 'Gone means gone.'],
            ],
        ],
        [
            'slug' => 'help',
            'title' => 'Help',
            'articles' => [
                ['slug' => 'faq', 'title' => 'FAQ', 'summary' => 'Short answers to common questions.'],
                ['slug' => 'contact-and-bug-reports', 'title' => 'Contact and bug reports', 'summary' => 'Where to report what.'],
            ],
        ],
    ],
];
