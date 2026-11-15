import type {
    OnboardingCategory,
    OnboardingInterest,
    OnboardingSource,
} from './onboarding';

export type SourceType =
    | 'feed'
    | 'github_trending'
    | 'resend_inbox'
    | 'imap_inbox';

export type ManagedSource = Omit<OnboardingSource, 'url'> & {
    url: string | null;
    has_key: boolean;
    address?: string | null;
    imap?: { host: string; username: string; folder: string } | null;
    interests: Omit<OnboardingInterest, 'selected'>[];
};

export type SourceInput = {
    type: Exclude<SourceType, 'github_trending'>;
    name: string;
    url: string;
    api_key: string;
    address: string;
    imap_host: string;
    imap_username: string;
    imap_password: string;
    imap_folder: string;
    interests: string[];
};

export type SourceTestResult = {
    ok: boolean;
    message: string;
    headlines: string[];
};

export type SourcesProps = {
    catalog: {
        interest: Omit<OnboardingInterest, 'selected'>;
        sources: ManagedSource[];
    }[];
    custom: ManagedSource[];
    interests: OnboardingInterest[];
    categories: OnboardingCategory[];
    counts: { total: number; failing: number };
};
