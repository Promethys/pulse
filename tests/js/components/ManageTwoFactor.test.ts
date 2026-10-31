import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

describe('ManageTwoFactor', () => {
    it.each([
        [true, true, true],
        [true, false, false],
        [false, true, false],
    ])(
        'explains disabling with canManage=%s enabled=%s',
        (canManageTwoFactor, twoFactorEnabled, visible) => {
            render(ManageTwoFactor, {
                props: { canManageTwoFactor, twoFactorEnabled },
                global: {
                    stubs: {
                        TwoFactorRecoveryCodes: true,
                        TwoFactorSetupModal: true,
                    },
                },
            });
            const message = screen.queryByText(
                'Turning this off removes the authenticator-code check when you sign in.',
            );
            if (visible) expect(message).toBeVisible();
            else expect(message).not.toBeInTheDocument();
        },
    );
});
