import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import RunDigestButton from '@/components/pulse/RunDigestButton.vue';
import { page } from '../../support/inertia';

describe('RunDigestButton', () => {
    it('posts the kind and displays the processing state', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        page.props = { errors: {} };
        render(RunDigestButton, { props: { kind: 'news', label: 'Generate' } });
        await userEvent.click(screen.getByRole('button', { name: 'Generate' }));
        expect(post).toHaveBeenCalledWith(
            '/digest/run',
            { kind: 'news' },
            expect.any(Object),
        );
        const options = post.mock.calls[0][2];
        options?.onStart?.({} as never);
        expect(
            await screen.findByRole('button', { name: 'Preparing…' }),
        ).toBeDisabled();
    });

    it('shows a validation error', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        page.props = { errors: {} };
        render(RunDigestButton, { props: { kind: 'news', label: 'Generate' } });
        await userEvent.click(screen.getByRole('button', { name: 'Generate' }));
        post.mock.calls[0][2]?.onError?.({ kind: 'Already started.' });
        expect(await screen.findByText('Already started.')).toBeVisible();
    });
});
