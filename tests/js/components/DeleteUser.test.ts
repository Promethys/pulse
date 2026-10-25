import { router } from '@inertiajs/vue3';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import DeleteUser from '@/components/DeleteUser.vue';

type VisitOptions = {
    method?: string;
    data?: Record<string, unknown>;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
    onStart?: () => void;
};

let visits: { url: string; options: VisitOptions }[] = [];

async function openDialog() {
    const user = userEvent.setup();
    render(DeleteUser, {
        container: document.body.appendChild(document.createElement('div')),
    });
    await user.click(screen.getByRole('button', { name: 'Delete account' }));

    return user;
}

describe('DeleteUser', () => {
    beforeEach(() => {
        visits = [];
        vi.spyOn(router, 'visit').mockImplementation((url, options) => {
            visits.push({
                url:
                    url instanceof URL
                        ? url.href
                        : typeof url === 'string'
                          ? url
                          : url.url,
                options: (options ?? {}) as VisitOptions,
            });
        });
    });

    it('warns without opening the confirmation', () => {
        render(DeleteUser);

        expect(
            screen.getByText('Delete your account and all of its resources'),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Please proceed with caution, this cannot be undone.',
            ),
        ).toBeVisible();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('asks for the password in a confirmation dialog', async () => {
        await openDialog();

        expect(
            screen.getByRole('dialog', {
                name: 'Are you sure you want to delete your account?',
            }),
        ).toBeVisible();
        expect(screen.getByPlaceholderText('Password')).toHaveAttribute(
            'type',
            'password',
        );
    });

    it('sends a delete request to the profile route with the password', async () => {
        const user = await openDialog();

        await user.type(screen.getByPlaceholderText('Password'), 's3cret');
        await user.click(
            screen.getByRole('button', { name: 'Delete account' }),
        );

        expect(visits).toHaveLength(1);
        // HTML forms only send GET and POST: the DELETE is spoofed with _method
        expect(visits[0].url).toContain('/settings/profile');
        expect(visits[0].url).toContain('_method=DELETE');
        expect(visits[0].options.method).toBe('post');
        expect(visits[0].options.data).toMatchObject({ password: 's3cret' });
    });

    it('shows the server error and focuses the password again', async () => {
        const user = await openDialog();
        await user.type(screen.getByPlaceholderText('Password'), 'wrong');
        await user.click(
            screen.getByRole('button', { name: 'Delete account' }),
        );

        visits[0].options.onError?.({ password: 'The password is incorrect.' });
        visits[0].options.onFinish?.();

        expect(
            await screen.findByText('The password is incorrect.'),
        ).toBeVisible();
        await waitFor(() =>
            expect(screen.getByPlaceholderText('Password')).toHaveFocus(),
        );
    });

    it('disables the confirm button while the request runs', async () => {
        const user = await openDialog();
        await user.click(
            screen.getByRole('button', { name: 'Delete account' }),
        );

        visits[0].options.onStart?.();

        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Delete account' }),
            ).toBeDisabled(),
        );
    });

    it('closes without sending anything on cancel, and starts clean next time', async () => {
        const user = await openDialog();
        await user.type(screen.getByPlaceholderText('Password'), 'typed');

        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        expect(visits).toHaveLength(0);

        await user.click(
            screen.getByRole('button', { name: 'Delete account' }),
        );
        expect(screen.getByPlaceholderText('Password')).toHaveValue('');
    });
});
