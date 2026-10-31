import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import Digest from '@/pages/settings/Digest.vue';
import { page } from '../../support/inertia';
import { digestProps } from '../../support/settingsProps';

function renderPage(props = digestProps(), errors = {}) {
    page.url = '/settings/digest';
    page.props = { ...props, errors };
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    render(Digest, { props });
    return { put, user: userEvent.setup() };
}

describe('Digest settings', () => {
    it('edits the profile, shows its counter and saves the complete digest settings', async () => {
        const props = digestProps();
        const view = renderPage(props);
        const profile = screen.getByRole('textbox', { name: /About you/i });
        expect(profile).toHaveValue(props.settings.profile);
        expect(profile).toHaveAttribute('maxlength', '2000');
        await view.user.clear(profile);
        await view.user.type(profile, 'Curious reader');
        expect(screen.getByText(/14\s*\/\s*2,?000/)).toBeVisible();
        await view.user.click(screen.getByRole('switch', { name: /^News$/ }));
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/settings/digest',
            {
                timezone: 'Europe/Paris',
                profile: 'Curious reader',
                news_enabled: false,
                news_time: '09:00',
                learning_enabled: true,
                learning_time: '09:30',
                max_items: 20,
            },
            expect.any(Object),
        );
    });

    it('disables unavailable learning with an explanation and permits saving it off', async () => {
        const props = digestProps();
        props.settings.learning_available = false;
        props.settings.learning_enabled = false;
        const view = renderPage(props);
        expect(
            screen.getByRole('switch', { name: /^Learning$/ }),
        ).toBeDisabled();
        expect(
            screen.getByText('Available with a learning interest'),
        ).toBeVisible();
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/settings/digest',
            expect.objectContaining({ learning_enabled: false }),
            expect.any(Object),
        );
    });

    it('offers only quarter-hour times and saves timezone and slider changes', async () => {
        const view = renderPage();
        for (const [label, option] of [
            ['Timezone', 'UTC'],
            ['News time', '10:15'],
            ['Learning time', '23:45'],
        ]) {
            const select = screen.getByRole('combobox', { name: label });
            if (select instanceof HTMLSelectElement) {
                expect(
                    Array.from(select.options).some((item) =>
                        /09:01|24:00/.test(item.text),
                    ),
                ).toBe(false);
                await view.user.selectOptions(select, option);
            } else {
                await view.user.click(select);
                expect(
                    screen.queryByRole('option', { name: '09:01' }),
                ).not.toBeInTheDocument();
                await view.user.click(
                    await screen.findByRole('option', { name: option }),
                );
            }
        }
        const slider = screen.getByRole('slider', { name: /Max.*items/i });
        expect(slider).toHaveAttribute('aria-valuemin', '1');
        expect(slider).toHaveAttribute('aria-valuemax', '20');
        slider.focus();
        await view.user.keyboard('{Home}');
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/settings/digest',
            expect.objectContaining({
                timezone: 'UTC',
                news_time: '10:15',
                learning_time: '23:45',
                max_items: 1,
            }),
            expect.any(Object),
        );
    });

    it('shows validation errors without discarding the profile', () => {
        renderPage(digestProps(), {
            profile: 'Profile is too long.',
            news_time: 'Choose a quarter hour.',
        });
        expect(screen.getByText('Profile is too long.')).toBeVisible();
        expect(screen.getByText('Choose a quarter hour.')).toBeVisible();
        expect(screen.getByRole('textbox', { name: /About you/i })).toHaveValue(
            'A curious reader',
        );
    });

    it('explains that the reader profile is shared with the AI provider', () => {
        renderPage();
        expect(
            screen.getByText('Sent to your AI provider to tailor your digest.'),
        ).toBeVisible();
    });
});
