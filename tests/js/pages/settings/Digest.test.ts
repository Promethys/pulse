import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import Digest from '@/pages/settings/Digest.vue';
import { page } from '../../support/inertia';
import { digestProps as baseDigestProps } from '../../support/settingsProps';

function digestProps() {
    const props = baseDigestProps();
    return {
        ...props,
        settings: {
            ...props.settings,
            paused: false,
            paused_until: null as string | null,
        },
    };
}

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
            screen.getByText(
                /Choose an interest marked.*Includes learning.*on/,
            ),
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
            screen.getByText(
                'Optional. Describe your experience, interests and what to avoid so the AI can choose and explain content for you. This text is sent to your AI provider.',
            ),
        ).toBeVisible();
    });
});

describe('Getting started guidance', () => {
    it.each([
        'Times use your selected timezone. Pulse checks every 15 minutes and starts preparing each digest when it is due. Delivery can take longer.',
        'Maximum news stories in each daily digest. You may receive fewer.',
    ])('explains %s', async (message) => {
        renderPage();
        expect(screen.getByText(message)).toBeVisible();
    });
});

it.each([true, false])(
    'links learning setup only when unavailable=%s',
    (unavailable) => {
        const props = digestProps();
        props.settings.learning_available = !unavailable;
        renderPage(props);
        expect(
            screen.getByRole('button', { name: 'Help: News limit' }),
        ).toBeVisible();
        const link = screen.queryByRole('link', { name: 'Sources' });
        if (unavailable) expect(link).toHaveAttribute('href', '/sources');
        else expect(link).not.toBeInTheDocument();
    },
);

describe('Pause digests', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-31T00:30:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('defaults to pausing until resumed and hides the date picker and quick picks', async () => {
        const { user, put } = renderPage();
        expect(
            screen.getByRole('radio', { name: 'Until I resume' }),
        ).toBeChecked();
        expect(
            screen.getByRole('radio', { name: 'Until a date' }),
        ).not.toBeChecked();
        expect(
            screen.queryByRole('button', { name: 'End date' }),
        ).not.toBeInTheDocument();
        for (const name of ['1 week', '2 weeks']) {
            expect(
                screen.queryByRole('button', { name }),
            ).not.toBeInTheDocument();
        }
        await user.click(screen.getByRole('button', { name: 'Pause digests' }));
        expect(put).toHaveBeenCalledWith(
            '/settings/digest/pause',
            { until: null },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(
            screen.getByRole('button', { name: 'Pause digests' }),
        ).toBeDisabled();
    });

    it.each([
        ['1 week', '6 Nov 2026', '2026-11-06'],
        ['2 weeks', '13 Nov 2026', '2026-11-13'],
    ])(
        'sets %s from the reader local day across a daylight saving change',
        async (pick, label, until) => {
            const props = digestProps();
            props.settings.timezone = 'America/New_York';
            const { user, put } = renderPage(props);
            await user.click(
                screen.getByRole('radio', { name: 'Until a date' }),
            );
            await user.click(screen.getByRole('button', { name: pick }));
            expect(
                screen.getByRole('radio', { name: 'Until a date' }),
            ).toBeChecked();
            expect(
                screen.getByRole('button', { name: 'End date' }),
            ).toHaveTextContent(label);
            await user.click(
                screen.getByRole('button', { name: 'Pause digests' }),
            );
            expect(put).toHaveBeenCalledWith(
                '/settings/digest/pause',
                { until },
                expect.objectContaining({ preserveScroll: true }),
            );
        },
    );

    it('discards a picked date when switching back to until resumed', async () => {
        const { user, put } = renderPage();
        await user.click(screen.getByRole('radio', { name: 'Until a date' }));
        await user.click(screen.getByRole('button', { name: '1 week' }));
        await user.click(screen.getByRole('radio', { name: 'Until I resume' }));
        expect(
            screen.queryByRole('button', { name: 'End date' }),
        ).not.toBeInTheDocument();
        for (const name of ['1 week', '2 weeks']) {
            expect(
                screen.queryByRole('button', { name }),
            ).not.toBeInTheDocument();
        }
        await user.click(screen.getByRole('button', { name: 'Pause digests' }));
        expect(put).toHaveBeenCalledWith(
            '/settings/digest/pause',
            { until: null },
            expect.any(Object),
        );
    });

    it('requires a date in date mode, including after clearing a quick pick', async () => {
        const { user, put } = renderPage();
        await user.click(screen.getByRole('radio', { name: 'Until a date' }));
        const submit = screen.getByRole('button', { name: 'Pause digests' });
        expect(submit).toBeDisabled();
        await user.click(screen.getByRole('button', { name: '1 week' }));
        expect(submit).toBeEnabled();
        await user.click(screen.getByRole('button', { name: 'End date' }));
        await user.click(await screen.findByRole('button', { name: 'Clear' }));
        expect(submit).toBeDisabled();
        expect(put).not.toHaveBeenCalled();
    });

    it('bounds the calendar from local today through 365 days ahead', async () => {
        const props = digestProps();
        props.settings.timezone = 'America/New_York';
        const { user } = renderPage(props);
        await user.click(screen.getByRole('radio', { name: 'Until a date' }));
        await user.click(screen.getByRole('button', { name: 'End date' }));
        await screen.findByRole('application');
        const day = (number: number) =>
            screen.getByRole('button', {
                name: new RegExp(
                    `\\b(?:${number} October|October ${number})\\b`,
                ),
            });
        expect(day(29)).toHaveAttribute('data-disabled');
        expect(day(30)).not.toHaveAttribute('data-disabled');
        for (let month = 0; month < 12; month++) {
            await user.click(screen.getByRole('button', { name: 'Next page' }));
        }
        expect(day(30)).not.toHaveAttribute('data-disabled');
        expect(day(31)).toHaveAttribute('data-disabled');
        await user.click(day(30));
        expect(
            screen.getByRole('button', { name: 'End date' }),
        ).toHaveTextContent('30 Oct 2027');
    });

    it('allows selecting an end date and associates validation errors with it', async () => {
        const { user, put } = renderPage(digestProps(), {
            until: 'The end date must not be before today.',
        });
        await user.click(screen.getByRole('radio', { name: 'Until a date' }));
        const picker = screen.getByRole('button', { name: 'End date' });
        expect(picker).toHaveAttribute('aria-invalid', 'true');
        expect(picker).toHaveAccessibleDescription(
            'The end date must not be before today.',
        );
        await user.click(picker);
        await user.click(
            await screen.findByRole('button', {
                name: /\b(?:31 October|October 31)\b/,
            }),
        );
        await user.click(screen.getByRole('button', { name: 'Pause digests' }));
        expect(put).toHaveBeenCalledWith(
            '/settings/digest/pause',
            { until: '2026-10-31' },
            expect.any(Object),
        );
    });

    it.each([
        [null, 'Digests are paused until you resume them.'],
        ['2026-11-30', 'Digests are paused until Monday 30 November.'],
    ] as const)(
        'shows the paused state ending %s and resumes without a pause form',
        async (until, message) => {
            const props = digestProps();
            props.settings.paused = true;
            props.settings.paused_until = until;
            const remove = vi
                .spyOn(router, 'delete')
                .mockImplementation(() => {});
            const { user } = renderPage(props);
            const status = screen.getByText(message);
            const resume = screen.getByRole('button', {
                name: 'Resume digests',
            });
            expect(status).toBeVisible();
            expect(status.parentElement).toHaveClass(
                'rounded-xl',
                'border',
                'border-primary-strong/30',
                'bg-primary/40',
                'p-4',
                'text-sm',
                'text-foreground',
                'dark:bg-primary/10',
            );
            expect(status.parentElement).toContainElement(resume);
            expect(screen.queryByRole('radio')).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Pause digests' }),
            ).not.toBeInTheDocument();
            await user.click(resume);
            expect(remove).toHaveBeenCalledWith(
                '/settings/digest/pause',
                expect.objectContaining({ preserveScroll: true }),
            );
            expect(
                screen.getByRole('button', { name: 'Resume digests' }),
            ).toBeDisabled();
        },
    );
});
