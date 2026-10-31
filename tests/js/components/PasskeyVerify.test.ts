import { render, screen } from '@testing-library/vue';
import { enableAutoUnmount, shallowMount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';

const state = vi.hoisted(() => ({ supported: true }));

vi.mock('@laravel/passkeys/vue', () => ({
    usePasskeyVerify: () => ({
        verify: vi.fn(),
        isLoading: false,
        error: null,
        isSupported: state.supported,
    }),
}));

enableAutoUnmount(afterEach);

describe('PasskeyVerify', () => {
    it.each([true, false])(
        'shows saved-passkey guidance with supported=%s',
        (supported) => {
            state.supported = supported;
            render(PasskeyVerify);
            const message = screen.queryByText(
                'Use a passkey you previously added to Pulse.',
            );
            if (supported) expect(message).toBeVisible();
            else expect(message).not.toBeInTheDocument();
        },
    );

    it('defines passkeys beside the sign-in option', () => {
        state.supported = true;
        const wrapper = shallowMount(PasskeyVerify);
        expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
            label: 'Passkeys',
            text: 'A way to sign in using your device or password manager instead of typing a password.',
        });
    });

    it('hides passkey help in unsupported browsers', () => {
        state.supported = false;
        const wrapper = shallowMount(PasskeyVerify);
        expect(wrapper.findComponent(HelpTip).exists()).toBe(false);
    });
});
