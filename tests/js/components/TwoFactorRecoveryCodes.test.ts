import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { http } from '../support/inertia';

afterEach(() => useTwoFactorAuth().clearTwoFactorAuthData());

describe('TwoFactorRecoveryCodes', () => {
    it('warns about replacing codes only when the regeneration action is visible', async () => {
        useTwoFactorAuth().recoveryCodesList.value = ['first-code'];
        render(TwoFactorRecoveryCodes);
        const message =
            'Generating new codes makes all previous recovery codes stop working. Save the new set.';
        expect(screen.queryByText(message)).not.toBeInTheDocument();
        const user = userEvent.setup();
        await user.click(
            screen.getByRole('button', { name: 'View recovery codes' }),
        );
        const warning = screen.getByText(message);
        expect(warning).toBeVisible();
        expect(
            warning.compareDocumentPosition(
                screen.getByRole('button', { name: 'Regenerate codes' }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        await user.click(
            screen.getByRole('button', { name: 'Hide recovery codes' }),
        );
        expect(screen.queryByText(message)).not.toBeInTheDocument();
    });

    it('does not show regeneration guidance when there are no codes', async () => {
        http.submit.mockResolvedValue([]);
        render(TwoFactorRecoveryCodes);
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'View recovery codes' }));
        expect(
            screen.queryByText(
                'Generating new codes makes all previous recovery codes stop working. Save the new set.',
            ),
        ).not.toBeInTheDocument();
    });
});
