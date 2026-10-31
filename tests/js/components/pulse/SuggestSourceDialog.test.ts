import { router } from '@inertiajs/vue3';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import SuggestSourceDialog from '@/components/pulse/SuggestSourceDialog.vue';
import { page } from '../../support/inertia';
import { onboardingProps } from '../../support/onboardingProps';

function sourceFixture() {
    const props = onboardingProps();
    return {
        ...props.sources[0].sources[0],
        id: 21,
        name: 'My blog',
        is_custom: true,
        is_default: false,
        interests: [props.interests[0]],
        has_key: false,
        suggested: false,
    };
}

async function renderDialog(source = sourceFixture(), open = true) {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    page.props = { errors: {} };
    const props = { open, source, interests: onboardingProps().interests };
    const view = render(SuggestSourceDialog, { props });
    if (open) await screen.findByRole('dialog');
    return { view, post, props, user: userEvent.setup() };
}

function submitButton() {
    return screen.getByRole('button', { name: /suggest|submit|send/i });
}

async function chooseInterest(
    view: Awaited<ReturnType<typeof renderDialog>>,
    name: string,
) {
    const select = screen.getByRole('combobox', { name: /^Interest$/i });
    if (select instanceof HTMLSelectElement) {
        await view.user.selectOptions(select, name);
    } else {
        await view.user.click(select);
        await view.user.click(await screen.findByRole('option', { name }));
    }
}

async function fillReason(view: Awaited<ReturnType<typeof renderDialog>>) {
    await view.user.type(
        screen.getByRole('textbox', { name: /^Reason$/i }),
        'Useful reporting for everyone.',
    );
}

describe('SuggestSourceDialog', () => {
    it('stays closed until opened and prefills the first source interest', async () => {
        const view = await renderDialog(sourceFixture(), false);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        await view.view.rerender({ ...view.props, open: true });
        await screen.findByRole('dialog');
        const select = screen.getByRole('combobox', { name: /^Interest$/i });
        if (select instanceof HTMLSelectElement)
            expect(select).toHaveValue('artificial-intelligence');
        else expect(select).toHaveTextContent('Artificial intelligence');
        expect(screen.getByRole('textbox', { name: /^Reason$/i })).toHaveValue(
            '',
        );
        expect(submitButton()).toBeDisabled();
        expect(screen.getByText(/0\s*\/\s*500/)).toBeVisible();
    });

    it('offers only live interests and sends only the chosen slug and reason', async () => {
        const view = await renderDialog();
        const select = screen.getByRole('combobox', { name: /^Interest$/i });
        if (!(select instanceof HTMLSelectElement))
            await view.user.click(select);
        expect(
            screen.queryByRole('option', { name: /Robotics/ }),
        ).not.toBeInTheDocument();
        if (!(select instanceof HTMLSelectElement))
            await view.user.keyboard('{Escape}');
        await chooseInterest(
            view,
            select instanceof HTMLSelectElement ? 'security' : 'Security',
        );
        await fillReason(view);
        await view.user.click(submitButton());
        expect(view.post).toHaveBeenCalledOnce();
        expect(view.post).toHaveBeenCalledWith(
            '/sources/21/suggest',
            {
                interest: 'security',
                reason: 'Useful reporting for everyone.',
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it.each(['none', 'draft'])(
        'requires choosing a live interest when the source has %s',
        async (state) => {
            const source = sourceFixture();
            source.interests =
                state === 'draft' ? [onboardingProps().interests[2]] : [];
            const view = await renderDialog(source);
            await fillReason(view);
            expect(submitButton()).toBeDisabled();
            expect(view.post).not.toHaveBeenCalled();
            await chooseInterest(
                view,
                screen.getByRole('combobox') instanceof HTMLSelectElement
                    ? 'security'
                    : 'Security',
            );
            expect(submitButton()).toBeEnabled();
        },
    );

    it('enforces the reason bounds and updates the character counter', async () => {
        const view = await renderDialog();
        const reason = screen.getByRole('textbox', { name: /^Reason$/i });
        await view.user.type(reason, '123456789');
        expect(screen.getByText(/9\s*\/\s*500/)).toBeVisible();
        expect(submitButton()).toBeDisabled();
        await view.user.type(reason, '0');
        expect(submitButton()).toBeEnabled();
        expect(reason).toHaveAttribute('maxlength', '500');
        await view.user.clear(reason);
        await view.user.click(reason);
        await view.user.paste('x'.repeat(500));
        await view.user.type(reason, 'y');
        expect(reason).toHaveValue('x'.repeat(500));
        expect(screen.getByText(/500\s*\/\s*500/)).toBeVisible();
    });

    it('shows server errors and preserves the reason for a retry', async () => {
        const view = await renderDialog();
        await fillReason(view);
        await view.user.click(submitButton());
        const options = view.post.mock.calls[0][2];
        options?.onError?.({
            interest: 'Choose a live interest.',
            reason: 'This feed does not have enough recent posts.',
        });
        await waitFor(() =>
            expect(
                screen.getByText(
                    'This feed does not have enough recent posts.',
                ),
            ).toBeVisible(),
        );
        expect(screen.getByText('Choose a live interest.')).toBeVisible();
        expect(screen.getByRole('textbox', { name: /^Reason$/i })).toHaveValue(
            'Useful reporting for everyone.',
        );
        expect(view.view.emitted('update:open')).toBeUndefined();
    });

    it('disables repeat submissions while processing and closes on success', async () => {
        const view = await renderDialog();
        await fillReason(view);
        await view.user.click(submitButton());
        const options = view.post.mock.calls[0][2];
        options?.onStart?.({} as never);
        await waitFor(() => expect(submitButton()).toBeDisabled());
        await view.user.click(submitButton());
        expect(view.post).toHaveBeenCalledOnce();
        options?.onSuccess?.({ props: {} } as never);
        await waitFor(() =>
            expect(view.view.emitted('update:open')).toContainEqual([false]),
        );
    });

    it('cancels without submitting and clears stale reason when reopened for another source', async () => {
        const view = await renderDialog();
        await fillReason(view);
        await view.user.keyboard('{Escape}');
        expect(view.view.emitted('update:open')).toContainEqual([false]);
        expect(view.post).not.toHaveBeenCalled();
        await view.view.rerender({ ...view.props, open: false });
        const source = {
            ...sourceFixture(),
            id: 22,
            interests: [onboardingProps().interests[1]],
        };
        await view.view.rerender({ ...view.props, open: true, source });
        await screen.findByRole('dialog');
        expect(screen.getByRole('textbox', { name: /^Reason$/i })).toHaveValue(
            '',
        );
        const select = screen.getByRole('combobox');
        if (select instanceof HTMLSelectElement)
            expect(select).toHaveValue('security');
        else expect(select).toHaveTextContent('Security');
    });
});
