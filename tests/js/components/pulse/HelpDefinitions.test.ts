import { enableAutoUnmount, shallowMount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vite-plus/test';
import AiKeyForm from '@/components/pulse/AiKeyForm.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import SourceFormDialog from '@/components/pulse/SourceFormDialog.vue';
import SourceRow from '@/components/pulse/SourceRow.vue';
import Onboarding from '@/pages/Onboarding.vue';
import Sources from '@/pages/Sources.vue';
import Delivery from '@/pages/settings/Delivery.vue';
import Digest from '@/pages/settings/Digest.vue';
import { page } from '../../support/inertia';
import { onboardingProps } from '../../support/onboardingProps';
import { deliveryProps, digestProps } from '../../support/settingsProps';

enableAutoUnmount(afterEach);

beforeEach(() => {
    page.props = { errors: {} };
});

const global = { renderStubDefaultSlot: true, stubs: { HelpTip: false } };

describe('Help definitions', () => {
    it.each([
        [
            'feed',
            'Feeds',
            'A feed lists a site’s latest posts so Pulse can read them automatically.',
        ],
        [
            'resend_inbox',
            'Resend',
            'Resend is an email service. This does not connect your Gmail or Outlook inbox.',
        ],
    ])('defines %s sources', (type, label, text) => {
        const onboarding = onboardingProps();
        const wrapper = shallowMount(SourceFormDialog, {
            props: {
                open: true,
                source: {
                    ...onboarding.sources[0].sources[0],
                    type,
                    interests: [],
                    has_key: false,
                },
                interests: onboarding.interests,
                errors: {},
            },
            global,
        });
        expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
            label,
            text,
        });
    });

    it('defines providers and models', () => {
        const wrapper = shallowMount(AiKeyForm, {
            props: {
                providers: onboardingProps().providers,
                modelValue: {
                    provider: 'gemini',
                    model: 'model',
                    api_key: '',
                    consent: false,
                },
            },
            global,
        });
        expect(
            wrapper.findAllComponents(HelpTip).map((tip) => tip.props()),
        ).toEqual([
            {
                label: 'Provider',
                text: 'Provider: the service that runs the AI.',
            },
            {
                label: 'Model',
                text: 'Model: the AI version used to select and write your digest.',
            },
        ]);
    });

    it('defines unknown source health', () => {
        const wrapper = shallowMount(SourceRow, {
            props: {
                source: {
                    ...onboardingProps().sources[0].sources[0],
                    health: 'unknown',
                    interests: [],
                    has_key: false,
                },
            },
            global,
        });
        expect(wrapper.findComponent(HelpTip).props('text')).toBe(
            'Unknown means Pulse has not recorded a successful fetch yet.',
        );
    });

    it('defines the news limit', () => {
        const wrapper = shallowMount(Digest, { props: digestProps(), global });
        expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
            label: 'News limit',
            text: 'This does not limit the daily tip and topic.',
        });
    });

    it('defines sources beside onboarding interest guidance', () => {
        const wrapper = shallowMount(Onboarding, {
            props: onboardingProps(),
            global,
        });
        expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
            label: 'Sources',
            text: 'A source is a site or newsletter that supplies news.',
        });
    });
});

it('defines sources beside the Sources interest picker', () => {
    const onboarding = onboardingProps();
    const wrapper = shallowMount(Sources, {
        props: {
            catalog: [],
            custom: [],
            interests: onboarding.interests,
            categories: onboarding.categories,
            counts: { total: 0, failing: 0 },
        },
        global,
    });
    expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
        label: 'Sources',
        text: 'A source is a site or newsletter that supplies news.',
    });
});

it('defines Discord webhooks', () => {
    const wrapper = shallowMount(Delivery, { props: deliveryProps(), global });
    expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
        label: 'Discord webhooks',
        text: 'A webhook is a private address Pulse uses to post messages to Discord.',
    });
});
