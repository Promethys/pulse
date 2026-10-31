import type { OnboardingProps } from './onboarding';

export type DigestSettingsProps = Pick<
    OnboardingProps,
    'settings' | 'timezones'
>;
export type AiKeysProps = {
    keys: OnboardingProps['ai_keys'];
    providers: OnboardingProps['providers'];
    uses_instance_ai_notice: boolean;
    uses_instance_ai: boolean;
};
export type DeliveryDestination = {
    id: number;
    type: string;
    name: string;
    kinds: string[];
    enabled: boolean;
    verified_at: string | null;
    target: {
        guild_id?: string | null;
        channel_id?: string | null;
        webhook?: string | null;
        address?: string | null;
    };
    latest_delivery: null | {
        id: number;
        status: string;
        attempts: number;
        last_error: string | null;
        delivered_at: string | null;
        updated_at: string | null;
    };
};
export type DeliveryGuild = {
    id: number;
    guild_id: string;
    name: string;
    forums: { id: string; name: string }[];
    forums_error: boolean;
    destinations: DeliveryDestination[];
};
export type DeliveryProps = {
    guilds: DeliveryGuild[];
    webhooks: DeliveryDestination[];
    email: DeliveryDestination | null;
    install_url: string | null;
    account_email: string;
};
