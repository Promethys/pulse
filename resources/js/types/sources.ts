import type {
    OnboardingCategory,
    OnboardingInterest,
    OnboardingSource,
} from './onboarding';

export type ManagedSource = Omit<OnboardingSource, 'url'> & {
    url: string | null;
    has_key: boolean;
    interests: Omit<OnboardingInterest, 'selected'>[];
};

export type SourceInput = {
    type: string;
    name: string;
    url: string;
    api_key: string;
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
