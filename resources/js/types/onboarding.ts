export type OnboardingInterest = {
    id: number;
    slug: string;
    name: string;
    category: string;
    icon: string;
    position: number;
    has_learning: boolean;
    status: string;
    selected: boolean;
};

export type OnboardingCategory = { value: string; label: string };

export type OnboardingSource = {
    id: number;
    slug: string;
    name: string;
    type: string;
    url: string;
    site_url: string | null;
    is_custom: boolean;
    is_default: boolean;
    enabled: boolean;
    retired: boolean;
    last_fetched_at: string | null;
    last_success_at: string | null;
    last_error: string | null;
    health: string;
    subscription_enabled: boolean;
    suggested?: boolean;
};

export type OnboardingSourceGroup = {
    interest: { slug: string; name: string };
    sources: OnboardingSource[];
};

export type OnboardingProvider = {
    value: string;
    label: string;
    key_url: string;
    suggested_models: string[];
};

export type AiKeyInput = {
    provider: string;
    model: string;
    api_key: string;
    consent: boolean;
};

export type KeyTestResult = { ok: boolean; message: string };

export type OnboardingProps = {
    interests: OnboardingInterest[];
    categories: OnboardingCategory[];
    sources: OnboardingSourceGroup[];
    settings: {
        timezone: string;
        profile: string | null;
        news_enabled: boolean;
        news_time: string;
        learning_enabled: boolean;
        learning_time: string;
        max_items: number;
        onboarded_at: string | null;
        learning_available: boolean;
    };
    timezones: string[];
    providers: OnboardingProvider[];
    ai_keys: {
        id: number;
        provider: string;
        model: string;
        key_suffix: string;
        position: number;
        consented_at: string;
        created_at: string | null;
    }[];
    email: string;
    email_delivery: boolean;
    uses_instance_ai: boolean;
};
