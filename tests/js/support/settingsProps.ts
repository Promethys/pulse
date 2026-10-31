import { onboardingProps } from './onboardingProps';

export function digestProps() {
    const onboarding = onboardingProps();
    return { settings: onboarding.settings, timezones: onboarding.timezones };
}

export function aiKeysProps() {
    return {
        keys: [
            {
                id: 11,
                provider: 'gemini',
                model: 'gemini-3.8-flash',
                key_suffix: 'abcd',
                position: 1,
                consented_at: '2026-10-28T09:00:00Z',
                created_at: null,
            },
            {
                id: 12,
                provider: 'groq',
                model: 'llama-3.3-70b-versatile',
                key_suffix: 'efgh',
                position: 2,
                consented_at: '2026-10-28T09:00:00Z',
                created_at: null,
            },
        ],
        providers: onboardingProps().providers,
        uses_instance_ai_notice: false,
    };
}

export function destinationProps() {
    return {
        id: 21,
        type: 'discord_webhook',
        name: 'News hook',
        kinds: ['news'],
        enabled: true,
        verified_at: null,
        target: { webhook: '…abcd' },
        latest_delivery: null as null | {
            id: number;
            status: string;
            attempts: number;
            last_error: string | null;
            delivered_at: string | null;
            updated_at: string | null;
        },
    };
}

export function deliveryProps() {
    return {
        guilds: [
            {
                id: 31,
                guild_id: '456',
                name: 'Developers',
                forums: [
                    { id: '789', name: 'Digest' },
                    { id: '790', name: 'Learning' },
                ],
                forums_error: false,
                destinations: [
                    {
                        ...destinationProps(),
                        id: 22,
                        type: 'discord_bot',
                        name: 'Digest forum',
                        target: { guild_id: '456', channel_id: '789' },
                    },
                ],
            },
        ],
        webhooks: [destinationProps()],
        email: null as
            | null
            | (Omit<ReturnType<typeof destinationProps>, 'target'> & {
                  target: { address: string };
              }),
        install_url: '/discord/install' as string | null,
        account_email: 'reader@example.test',
    };
}
