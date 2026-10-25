import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import InputError from '@/components/InputError.vue';

describe('InputError', () => {
    it('shows the message', () => {
        render(InputError, { props: { message: 'The email is required.' } });

        expect(screen.getByText('The email is required.')).toBeVisible();
    });

    it.each([undefined, ''])('stays hidden with message %j', (message) => {
        const { container } = render(InputError, { props: { message } });

        expect(container.firstElementChild).not.toBeVisible();
    });

    it('updates when the message changes', async () => {
        const view = render(InputError, {
            props: { message: 'Too short.' },
        });

        await view.rerender({ message: 'Too long.' });
        expect(screen.getByText('Too long.')).toBeVisible();

        await view.rerender({ message: undefined });
        expect(view.container.firstElementChild).not.toBeVisible();
    });
});
