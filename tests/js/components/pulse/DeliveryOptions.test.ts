import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import type { Component } from 'vue';
import DeliveryOptions from '@/components/pulse/DeliveryOptions.vue';
import { onboardingProps } from '../../support/onboardingProps';

describe('DeliveryOptions', () => {
    it('locks in-app delivery on and reveals the account email only when enabled', async () => {
        const { email } = onboardingProps();
        const result = render<Component>(DeliveryOptions, {
            props: { email, emailDelivery: false },
        });
        expect(
            screen.getByRole('checkbox', { name: 'In the app' }),
        ).toBeChecked();
        expect(
            screen.getByRole('checkbox', { name: 'In the app' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('switch', { name: /^Email$/ }),
        ).not.toBeChecked();
        expect(
            screen.queryByRole('textbox', { name: 'Email address' }),
        ).not.toBeInTheDocument();

        await userEvent
            .setup()
            .click(screen.getByRole('switch', { name: /^Email$/ }));
        expect(result.emitted()['update:emailDelivery']).toEqual([[true]]);
        await result.rerender({ emailDelivery: true });
        expect(
            screen.getByRole('textbox', { name: 'Email address' }),
        ).toHaveValue(email);
    });

    it('resumes enabled email delivery, emits address edits and can turn email off', async () => {
        const result = render<Component>(DeliveryOptions, {
            props: { email: 'saved@example.test', emailDelivery: true },
        });
        const user = userEvent.setup();
        const address = screen.getByRole('textbox', { name: 'Email address' });
        expect(address).toHaveValue('saved@example.test');
        await user.clear(address);
        await user.type(address, 'new@example.test');
        expect(result.emitted()['update:email']).toContainEqual([
            'new@example.test',
        ]);
        await user.click(screen.getByRole('switch', { name: /^Email$/ }));
        expect(result.emitted()['update:emailDelivery']).toEqual([[false]]);
        await result.rerender({ emailDelivery: false });
        expect(
            screen.queryByRole('textbox', { name: 'Email address' }),
        ).not.toBeInTheDocument();
    });
});
