import { router } from '@inertiajs/vue3';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import SuggestInterestDialog from '@/components/pulse/SuggestInterestDialog.vue';
import { page } from '../../support/inertia';
import { onboardingProps } from '../../support/onboardingProps';

async function renderDialog(open = true) {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    page.props = { errors: {} };
    const props = { open, categories: onboardingProps().categories };
    const view = render(SuggestInterestDialog, { props });
    if (open) await screen.findByRole('dialog');
    return { view, post, props, user: userEvent.setup() };
}

function submitButton() {
    return screen.getByRole('button', { name: /suggest|submit|send/i });
}

async function chooseCategory(view: Awaited<ReturnType<typeof renderDialog>>) {
    const select = screen.getByRole('combobox', { name: /^Category$/i });
    if (select instanceof HTMLSelectElement)
        await view.user.selectOptions(select, 'technology');
    else {
        await view.user.click(select);
        await view.user.click(
            await screen.findByRole('option', {
                name: 'Technology',
            }),
        );
    }
}

async function fillForm(view: Awaited<ReturnType<typeof renderDialog>>) {
    await view.user.type(
        screen.getByRole('textbox', { name: /^Name$/i }),
        'Space Science',
    );
    await chooseCategory(view);
    await view.user.type(
        screen.getByRole('textbox', { name: /^Reason$/i }),
        'More reporting on space research.',
    );
}

describe('SuggestInterestDialog', () => {
    it('stays closed until opened and requires all fields', async () => {
        const view = await renderDialog(false);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        await view.view.rerender({ ...view.props, open: true });
        await screen.findByRole('dialog');
        expect(submitButton()).toBeDisabled();
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name$/i }),
            'Space Science',
        );
        expect(submitButton()).toBeDisabled();
        await view.user.type(
            screen.getByRole('textbox', { name: /^Reason$/i }),
            'More reporting on space research.',
        );
        expect(submitButton()).toBeDisabled();
        await chooseCategory(view);
        expect(submitButton()).toBeEnabled();
    });

    it('submits the name, category value and reason', async () => {
        const view = await renderDialog();
        await fillForm(view);
        await view.user.click(submitButton());
        expect(view.post).toHaveBeenCalledOnce();
        expect(view.post).toHaveBeenCalledWith(
            '/interest-proposals',
            {
                name: 'Space Science',
                category: 'technology',
                reason: 'More reporting on space research.',
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('enforces minimum and maximum name and reason lengths', async () => {
        const view = await renderDialog();
        await chooseCategory(view);
        const name = screen.getByRole('textbox', { name: /^Name$/i });
        const reason = screen.getByRole('textbox', { name: /^Reason$/i });
        await view.user.type(name, 'A');
        await view.user.type(reason, '1234567890');
        expect(submitButton()).toBeDisabled();
        await view.user.type(name, 'I');
        expect(submitButton()).toBeEnabled();
        await view.user.clear(reason);
        await view.user.type(reason, '123456789');
        expect(submitButton()).toBeDisabled();
        expect(name).toHaveAttribute('maxlength', '60');
        expect(reason).toHaveAttribute('maxlength', '500');
        await view.user.clear(name);
        await view.user.click(name);
        await view.user.paste('x'.repeat(60));
        await view.user.type(name, 'y');
        expect(name).toHaveValue('x'.repeat(60));
        await view.user.clear(reason);
        await view.user.click(reason);
        await view.user.paste('x'.repeat(500));
        await view.user.type(reason, 'y');
        expect(reason).toHaveValue('x'.repeat(500));
        expect(submitButton()).toBeEnabled();
    });

    it('shows all server validation errors without closing or losing the entered values', async () => {
        const view = await renderDialog();
        await fillForm(view);
        await view.user.click(submitButton());
        const errors = {
            name: 'This interest has already been suggested.',
            category: 'Choose a category.',
            reason: 'You already have 10 open proposals.',
        };
        view.post.mock.calls[0][2]?.onError?.(errors);
        for (const message of Object.values(errors))
            expect(await screen.findByText(message)).toBeVisible();
        expect(screen.getByRole('textbox', { name: /^Name$/i })).toHaveValue(
            'Space Science',
        );
        expect(screen.getByRole('textbox', { name: /^Reason$/i })).toHaveValue(
            'More reporting on space research.',
        );
        expect(view.view.emitted('update:open')).toBeUndefined();
    });

    it('disables duplicate submission while processing and closes only on success', async () => {
        const view = await renderDialog();
        await fillForm(view);
        await view.user.click(submitButton());
        const options = view.post.mock.calls[0][2];
        options?.onStart?.({} as never);
        await waitFor(() => expect(submitButton()).toBeDisabled());
        await view.user.click(submitButton());
        expect(view.post).toHaveBeenCalledOnce();
        expect(view.view.emitted('update:open')).toBeUndefined();
        options?.onSuccess?.({ props: {} } as never);
        await waitFor(() =>
            expect(view.view.emitted('update:open')).toContainEqual([false]),
        );
    });

    it('cancels without a request and reopens a clean form', async () => {
        const view = await renderDialog();
        await fillForm(view);
        await view.user.keyboard('{Escape}');
        expect(view.view.emitted('update:open')).toContainEqual([false]);
        expect(view.post).not.toHaveBeenCalled();
        await view.view.rerender({ ...view.props, open: false });
        await view.view.rerender({ ...view.props, open: true });
        await screen.findByRole('dialog');
        expect(screen.getByRole('textbox', { name: /^Name$/i })).toHaveValue(
            '',
        );
        expect(screen.getByRole('textbox', { name: /^Reason$/i })).toHaveValue(
            '',
        );
        expect(submitButton()).toBeDisabled();
    });
});
