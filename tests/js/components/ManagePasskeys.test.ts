import { enableAutoUnmount, shallowMount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';

enableAutoUnmount(afterEach);

describe('ManagePasskeys', () => {
    it('defines passkeys beside their settings', () => {
        const wrapper = shallowMount(ManagePasskeys, {
            props: { canManagePasskeys: true },
        });
        expect(wrapper.findComponent(HelpTip).props()).toMatchObject({
            label: 'Passkeys',
            text: 'A way to sign in using your device or password manager instead of typing a password.',
        });
    });

    it('hides passkey help when passkeys cannot be managed', () => {
        const wrapper = shallowMount(ManagePasskeys);
        expect(wrapper.findComponent(HelpTip).exists()).toBe(false);
    });
});
