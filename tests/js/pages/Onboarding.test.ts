import type { InertiaConfig, Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import Onboarding from '@/pages/Onboarding.vue';
import {
    about,
    aiKey,
    complete,
    interests,
    sources,
} from '@/actions/App/Http/Controllers/OnboardingController';
import { page } from '../support/inertia';
import { onboardingProps, onboardingSteps } from '../support/onboardingProps';

function renderPage(props = onboardingProps()) {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    page.url = '/onboarding';
    page.props = { ...props, errors: {} };
    const view = render(Onboarding, { props });
    return { ...view, props, post, put, user: userEvent.setup() };
}

type View = ReturnType<typeof renderPage>;

async function saved(view: View, method: 'post' | 'put', url: string) {
    await view.user.click(screen.getByRole('button', { name: /^Continue$/ }));
    const request = view[method].mock.calls.at(-1);
    expect(request?.[0]).toBe(url);
    expect(request?.[2]?.onSuccess).toBeTypeOf('function');
    request?.[2]?.onSuccess?.({ props: view.props } as unknown as Page<
        InertiaConfig['sharedPageProps']
    >);
    await nextTick();
}

async function goToStep(view: View, step: number) {
    if (step >= 1) await saved(view, 'post', interests.url());
    if (step >= 2) await saved(view, 'put', sources.url());
    if (step >= 3)
        await view.user.click(
            screen.getByRole('button', { name: 'Skip for now' }),
        );
    if (step >= 4)
        await view.user.click(
            screen.getByRole('button', { name: 'Skip for now' }),
        );
}

async function choose(view: View, label: string, option: string) {
    const select = screen.getByRole('combobox', { name: label });
    if (select instanceof HTMLSelectElement) {
        await view.user.selectOptions(select, option);
    } else {
        await view.user.click(select);
        await view.user.click(
            await screen.findByRole('option', { name: option }),
        );
    }
}

async function fillKey(view: View) {
    await view.user.type(
        screen.getByLabelText('Gemini API key'),
        'fake-private-key',
    );
    await view.user.click(
        screen.getByRole('checkbox', {
            name: /stored encrypted.*own digests/i,
        }),
    );
}

describe('Onboarding', () => {
    it.each(['cancel', 'success', 'error'])(
        'preserves the interests step, search and selection after a suggestion %s',
        async (outcome) => {
            const view = renderPage();
            await view.user.click(
                screen.getByRole('button', { name: 'Security' }),
            );
            await view.user.type(
                screen.getByRole('textbox', { name: 'Search interests' }),
                'security',
            );
            await view.user.click(
                screen.getByRole('button', { name: 'Suggest an interest' }),
            );
            const dialog = await screen.findByRole('dialog');
            if (outcome === 'cancel') {
                await view.user.keyboard('{Escape}');
                expect(view.post).not.toHaveBeenCalled();
            } else {
                await view.user.type(
                    within(dialog).getByRole('textbox', { name: /^Name$/i }),
                    'Space Science',
                );
                const category = within(dialog).getByRole('combobox', {
                    name: /^Category$/i,
                });
                if (category instanceof HTMLSelectElement)
                    await view.user.selectOptions(category, 'technology');
                else {
                    await view.user.click(category);
                    await view.user.click(
                        await screen.findByRole('option', {
                            name: 'Technology',
                        }),
                    );
                }
                await view.user.type(
                    within(dialog).getByRole('textbox', { name: /^Reason$/i }),
                    'More reporting on space research.',
                );
                await view.user.click(
                    within(dialog).getByRole('button', {
                        name: /suggest|submit|send/i,
                    }),
                );
                expect(view.post).toHaveBeenCalledWith(
                    '/interest-proposals',
                    {
                        name: 'Space Science',
                        category: 'technology',
                        reason: 'More reporting on space research.',
                    },
                    expect.any(Object),
                );
                const options = view.post.mock.calls[0][2];
                if (outcome === 'success')
                    options?.onSuccess?.({ props: view.props } as never);
                else {
                    options?.onError?.({
                        name: 'This interest has already been suggested.',
                    });
                    expect(
                        await screen.findByText(
                            'This interest has already been suggested.',
                        ),
                    ).toBeVisible();
                    await view.user.keyboard('{Escape}');
                }
            }
            await waitFor(() =>
                expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
            );
            expect(
                screen.getByRole('listitem', { current: 'step' }),
            ).toHaveTextContent('Interests');
            expect(
                screen.getByRole('textbox', { name: 'Search interests' }),
            ).toHaveValue('security');
            expect(
                screen.getByRole('button', { name: 'Security' }),
            ).toHaveAttribute('aria-pressed', 'true');
            expect(view.put).not.toHaveBeenCalled();
            view.post.mockClear();
            await view.user.click(
                screen.getByRole('button', { name: /^Continue$/ }),
            );
            expect(view.post).toHaveBeenCalledWith(
                interests.url(),
                {
                    interests: [
                        'artificial-intelligence',
                        'programming',
                        'security',
                    ],
                },
                expect.any(Object),
            );
        },
    );

    it('groups interests by category in props order and resumes selected live chips', () => {
        const props = onboardingProps();
        props.categories.reverse();
        renderPage(props);

        const headings = screen
            .getAllByRole('heading')
            .filter((heading) =>
                /Technology|Software development/.test(
                    heading.textContent ?? '',
                ),
            );
        expect(headings.map((heading) => heading.textContent)).toEqual([
            expect.stringContaining('Software development'),
            expect.stringContaining('Technology'),
        ]);
        expect(
            screen.getByRole('button', { name: 'Artificial intelligence' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: 'Programming' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: 'Security' }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(
            screen.getByRole('button', { name: /Robotics.*Coming soon/i }),
        ).toBeDisabled();
        expect(
            screen.getByText(/Pick at least one\. 2 selected\./),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: /Suggest an interest/i }),
        ).toBeVisible();
    });

    it('hosts toasts, because it has no app layout', () => {
        renderPage();

        expect(
            document.querySelector('section[aria-label^="Notifications"]'),
        ).toBeInTheDocument();
    });

    it('requires at least one live interest, updates the count and never selects drafts', async () => {
        const props = onboardingProps();
        props.interests.forEach((interest) => {
            interest.selected = false;
        });
        const view = renderPage(props);
        const button = screen.getByRole('button', { name: /^Continue$/ });
        expect(button).toBeDisabled();
        expect(
            screen.getByText('Pick at least one interest to continue.'),
        ).toBeVisible();
        await view.user.click(
            screen.getByRole('button', { name: /Robotics.*Coming soon/i }),
        );
        expect(button).toBeDisabled();
        await view.user.click(screen.getByRole('button', { name: 'Security' }));
        expect(button).toBeEnabled();
        expect(
            screen.getByText(/Pick at least one\. 1 selected\./),
        ).toBeVisible();
        expect(
            screen.queryByText('Pick at least one interest to continue.'),
        ).not.toBeInTheDocument();
        await view.user.click(screen.getByRole('button', { name: 'Security' }));
        expect(button).toBeDisabled();
        expect(view.post).not.toHaveBeenCalled();
    });

    it('filters by name without case sensitivity, shows no matches and preserves selection', async () => {
        const view = renderPage();
        const search = screen.getByRole('textbox', {
            name: 'Search interests',
        });
        await view.user.type(search, 'sEcUr');
        expect(screen.getByRole('button', { name: 'Security' })).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Programming' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: /Software development/ }),
        ).not.toBeInTheDocument();
        await view.user.clear(search);
        await view.user.type(search, 'no-such-interest');
        expect(screen.getByText(/No interest matches/)).toBeVisible();
        await view.user.clear(search);
        expect(
            screen.getByRole('button', { name: 'Programming' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.queryByText(/No interest matches/),
        ).not.toBeInTheDocument();
    });

    it('posts selected slugs and waits for success before advancing to sources', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Programming' }),
        );
        await view.user.click(screen.getByRole('button', { name: 'Security' }));
        await view.user.click(
            screen.getByRole('button', { name: /^Continue$/ }),
        );

        expect(view.post).toHaveBeenCalledOnce();
        const [url, payload, options] = view.post.mock.calls[0];
        expect(url).toBe(interests.url());
        expect(payload).toEqual({
            interests: ['artificial-intelligence', 'security'],
        });
        expect(
            screen.getByRole('listitem', { current: 'step' }),
        ).toHaveTextContent('Interests');
        options?.onSuccess?.({ props: view.props } as unknown as Page<
            InertiaConfig['sharedPageProps']
        >);
        await nextTick();
        expect(
            screen.getByRole('heading', { name: 'Your sources' }),
        ).toBeVisible();
    });

    it('uses the source groups returned by the interests save for a new reader', async () => {
        const props = onboardingProps();
        const groups = props.sources;
        props.sources = [];
        props.interests.forEach((interest) => {
            interest.selected = false;
        });
        const view = renderPage(props);
        await view.user.click(
            screen.getByRole('button', { name: 'Programming' }),
        );
        await view.user.click(screen.getByRole('button', { name: 'Continue' }));
        const refreshed = { ...props, sources: [groups[1]] };
        await view.rerender(refreshed);
        view.post.mock.calls[0][2]?.onSuccess?.({
            props: refreshed,
        } as unknown as Page<InertiaConfig['sharedPageProps']>);
        await nextTick();

        expect(
            screen.getByRole('heading', { name: 'Programming' }),
        ).toBeVisible();
        expect(
            screen.getByRole('switch', { name: 'Use GitHub Trending' }),
        ).toBeChecked();
        expect(
            screen.queryByRole('switch', { name: 'Use Research' }),
        ).not.toBeInTheDocument();
    });

    it('shows source groups from the server and saves subscription choices by source id', async () => {
        const view = renderPage();
        await goToStep(view, 1);
        expect(
            screen.getByRole('heading', { name: 'Artificial intelligence' }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'Programming' }),
        ).toBeVisible();
        expect(screen.getAllByRole('switch')).toHaveLength(3);
        const first = view.props.sources[0].sources[0];
        expect(
            screen.getByRole('switch', { name: `Use ${first.name}` }),
        ).toBeChecked();
        expect(
            screen.getByRole('switch', { name: 'Use Research' }),
        ).not.toBeChecked();
        expect(screen.getByText(first.name)).toHaveClass('line-clamp-1');
        expect(screen.getByText(first.name)).toHaveAttribute(
            'title',
            first.name,
        );
        await view.user.click(
            screen.getByRole('switch', { name: `Use ${first.name}` }),
        );
        await view.user.click(
            screen.getByRole('switch', { name: 'Use Research' }),
        );
        await saved(view, 'put', sources.url());
        expect(view.put.mock.calls[0][1]).toEqual({
            subscriptions: { 11: false, 12: true, 13: true },
        });
        expect(
            screen.getByRole('heading', { name: 'About you' }),
        ).toBeVisible();
    });

    it('allows a selected interest with no default sources to continue with an empty map', async () => {
        const props = onboardingProps();
        props.sources = [{ interest: props.sources[0].interest, sources: [] }];
        const view = renderPage(props);
        await goToStep(view, 1);
        expect(
            screen.getByRole('heading', { name: 'Artificial intelligence' }),
        ).toBeVisible();
        expect(screen.queryByRole('switch')).not.toBeInTheDocument();
        await saved(view, 'put', sources.url());
        expect(view.put.mock.calls[0][1]).toEqual({ subscriptions: {} });
    });

    it('resumes profile and timezone, counts characters and saves edited values', async () => {
        const view = renderPage();
        await goToStep(view, 2);
        const profile = screen.getByRole('textbox', {
            name: 'Who are you and what do you want from Pulse?',
        });
        expect(profile).toHaveValue('A curious reader');
        expect(profile).toHaveAttribute('maxlength', '2000');
        expect(screen.getByText(/16\s*\/\s*2,?000/)).toBeVisible();
        const timezone = screen.getByRole('combobox', { name: 'Timezone' });
        if (timezone instanceof HTMLSelectElement)
            expect(timezone).toHaveValue('Europe/Paris');
        else expect(timezone).toHaveTextContent('Europe/Paris');
        await view.user.clear(profile);
        await view.user.type(profile, 'Backend developer');
        expect(screen.getByText(/17\s*\/\s*2,?000/)).toBeVisible();
        await choose(view, 'Timezone', 'America/New_York');
        await saved(view, 'put', about.url());
        expect(view.put.mock.calls.at(-1)?.[1]).toEqual({
            profile: 'Backend developer',
            timezone: 'America/New_York',
        });
        expect(
            screen.getByRole('heading', { name: 'Your AI key' }),
        ).toBeVisible();
    });

    it('limits the profile to 2000 characters including the displayed counter', async () => {
        const view = renderPage();
        await goToStep(view, 2);
        const profile = screen.getByRole('textbox', {
            name: 'Who are you and what do you want from Pulse?',
        });
        await view.user.clear(profile);
        await view.user.click(profile);
        await view.user.paste('x'.repeat(2000));
        await view.user.type(profile, 'y');
        expect(profile).toHaveValue('x'.repeat(2000));
        expect(screen.getByText(/2,?000\s*\/\s*2,?000/)).toBeVisible();
        await saved(view, 'put', about.url());
        expect(view.put.mock.calls.at(-1)?.[1]).toMatchObject({
            profile: 'x'.repeat(2000),
        });
    });

    it('saves an empty optional profile with the saved timezone', async () => {
        const props = onboardingProps();
        props.settings.profile = null;
        const view = renderPage(props);
        await goToStep(view, 2);
        expect(
            screen.getByRole('textbox', {
                name: 'Who are you and what do you want from Pulse?',
            }),
        ).toHaveValue('');
        expect(screen.getByText(/0\s*\/\s*2,?000/)).toBeVisible();
        await saved(view, 'put', about.url());
        expect(view.put.mock.calls.at(-1)?.[1]).toEqual({
            profile: expect.toBeOneOf(['', null]),
            timezone: 'Europe/Paris',
        });
    });

    it.each([2, 3])(
        'skips optional step %i without making a request',
        async (step) => {
            const view = renderPage();
            await goToStep(view, step);
            if (step === 2)
                await view.user.type(
                    screen.getByRole('textbox', {
                        name: 'Who are you and what do you want from Pulse?',
                    }),
                    ' unsaved',
                );
            else
                await view.user.type(
                    screen.getByLabelText('Gemini API key'),
                    'unsaved-key',
                );
            view.post.mockClear();
            view.put.mockClear();
            await view.user.click(
                screen.getByRole('button', { name: 'Skip for now' }),
            );
            expect(view.post).not.toHaveBeenCalled();
            expect(view.put).not.toHaveBeenCalled();
            expect(
                screen.getByRole('listitem', { current: 'step' }),
            ).toHaveTextContent(onboardingSteps[step + 1]);
        },
    );

    it('requires a key and consent before saving the default provider and model', async () => {
        const view = renderPage();
        await goToStep(view, 3);
        const button = screen.getByRole('button', { name: /^Continue$/ });
        expect(button).toBeDisabled();
        await view.user.type(
            screen.getByLabelText('Gemini API key'),
            'fake-private-key',
        );
        expect(button).toBeDisabled();
        await view.user.click(
            screen.getByRole('checkbox', {
                name: /stored encrypted.*own digests/i,
            }),
        );
        expect(button).toBeEnabled();
        const key = screen.getByLabelText('Gemini API key');
        await view.user.clear(key);
        expect(button).toBeDisabled();
        await view.user.type(key, 'fake-private-key');
        await saved(view, 'post', aiKey.url());
        expect(view.post.mock.calls.at(-1)?.[1]).toEqual({
            provider: 'gemini',
            model: 'gemini-3.8-flash',
            api_key: 'fake-private-key',
            consent: true,
        });
        expect(
            screen.getByRole('listitem', { current: 'step' }),
        ).toHaveTextContent('Delivery');
    });

    it('submits a custom model for the selected provider instead of Other model', async () => {
        const view = renderPage();
        await goToStep(view, 3);
        await view.user.click(screen.getByRole('radio', { name: 'Z.AI' }));
        await choose(view, 'Model', 'Other model');
        const button = screen.getByRole('button', { name: /^Continue$/ });
        await view.user.type(
            screen.getByLabelText('Z.AI API key'),
            'fake-zai-key',
        );
        await view.user.click(
            screen.getByRole('checkbox', {
                name: /stored encrypted.*own digests/i,
            }),
        );
        expect(button).toBeDisabled();
        await view.user.type(
            screen.getByRole('textbox', { name: /Other model|Model name/i }),
            'my-custom-model',
        );
        await saved(view, 'post', aiKey.url());
        expect(view.post.mock.calls.at(-1)?.[1]).toEqual({
            provider: 'zai',
            model: 'my-custom-model',
            api_key: 'fake-zai-key',
            consent: true,
        });
    });

    it.each([false, true])(
        'completes onboarding with email delivery %j',
        async (enabled) => {
            const view = renderPage();
            await goToStep(view, 4);
            expect(
                screen.getByRole('checkbox', { name: 'In the app' }),
            ).toBeChecked();
            expect(
                screen.getByRole('checkbox', { name: 'In the app' }),
            ).toBeDisabled();
            expect(screen.queryByText(/^Discord$/)).not.toBeInTheDocument();
            if (enabled) {
                await view.user.click(
                    screen.getByRole('switch', { name: /^Email$/ }),
                );
                const address = screen.getByRole('textbox', {
                    name: 'Email address',
                });
                expect(address).toHaveValue(view.props.email);
                await view.user.clear(address);
                await view.user.type(address, 'digest@example.test');
            }
            view.post.mockClear();
            await view.user.click(
                screen.getByRole('button', { name: 'Go to Today' }),
            );
            expect(view.post).toHaveBeenCalledOnce();
            expect(view.post.mock.calls[0][0]).toBe(complete.url());
            expect(view.post.mock.calls[0][1]).toMatchObject({
                email_delivery: enabled,
            });
            if (enabled)
                expect(view.post.mock.calls[0][1]).toMatchObject({
                    email: 'digest@example.test',
                });
        },
    );

    it('resumes enabled delivery with its saved address and can disable it', async () => {
        const props = onboardingProps();
        props.email = 'saved-delivery@example.test';
        props.email_delivery = true;
        const view = renderPage(props);
        await goToStep(view, 4);
        expect(screen.getByRole('switch', { name: /^Email$/ })).toBeChecked();
        expect(
            screen.getByRole('textbox', { name: 'Email address' }),
        ).toHaveValue(props.email);
        await view.user.click(screen.getByRole('switch', { name: /^Email$/ }));
        expect(
            screen.queryByRole('textbox', { name: 'Email address' }),
        ).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('button', { name: 'Go to Today' }),
        );
        expect(view.post.mock.calls.at(-1)?.[1]).toMatchObject({
            email_delivery: false,
        });
    });

    it.each([0, 1, 2, 3, 4])(
        'shows the footer controls for step %i and Back returns without saving',
        async (step) => {
            const view = renderPage();
            await goToStep(view, step);
            const back = screen.getByRole('button', { name: 'Back' });
            if (step === 0) expect(back).toBeDisabled();
            else expect(back).toBeEnabled();
            expect(
                screen.getByRole('listitem', { current: 'step' }),
            ).toHaveTextContent(onboardingSteps[step]);
            if (step === 2 || step === 3)
                expect(
                    screen.getByRole('button', { name: 'Skip for now' }),
                ).toBeVisible();
            else
                expect(
                    screen.queryByRole('button', { name: 'Skip for now' }),
                ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', {
                    name: step === 4 ? 'Go to Today' : 'Continue',
                }),
            ).toBeVisible();
            expect(
                screen.queryByRole('button', {
                    name: step === 4 ? 'Continue' : 'Go to Today',
                }),
            ).not.toBeInTheDocument();
            view.post.mockClear();
            view.put.mockClear();
            await view.user.click(back);
            expect(view.post).not.toHaveBeenCalled();
            expect(view.put).not.toHaveBeenCalled();
            expect(
                screen.getByRole('listitem', { current: 'step' }),
            ).toHaveTextContent(onboardingSteps[Math.max(0, step - 1)]);
        },
    );

    it.each([
        {
            step: 0,
            method: 'post' as const,
            field: 'interests',
            message: 'Choose at least one live interest.',
        },
        {
            step: 1,
            method: 'put' as const,
            field: 'subscriptions',
            message: 'Invalid subscriptions.',
        },
        {
            step: 2,
            method: 'put' as const,
            field: 'profile',
            message: 'The profile is too long.',
        },
        {
            step: 3,
            method: 'post' as const,
            field: 'api_key',
            message: 'You may store at most five keys.',
        },
        {
            step: 4,
            method: 'post' as const,
            field: 'email',
            message: 'Enter a valid email address.',
        },
    ])(
        'keeps step $step and displays its save failure',
        async ({ step, method, field, message }) => {
            const view = renderPage();
            await goToStep(view, step);
            if (step === 3) await fillKey(view);
            if (step === 4)
                await view.user.click(
                    screen.getByRole('switch', { name: /^Email$/ }),
                );
            view[method].mockClear();
            await view.user.click(
                screen.getByRole('button', {
                    name: step === 4 ? 'Go to Today' : 'Continue',
                }),
            );
            expect(view[method]).toHaveBeenCalledOnce();
            const options = view[method].mock.calls[0][2];
            page.props.errors = { [field]: message };
            options?.onError?.({ [field]: message });
            await nextTick();
            await waitFor(() =>
                expect(screen.getByText(message)).toBeVisible(),
            );
            expect(
                screen.getByRole('listitem', { current: 'step' }),
            ).toHaveTextContent(onboardingSteps[step]);
            if (step === 3)
                expect(screen.getByLabelText('Gemini API key')).toHaveValue(
                    'fake-private-key',
                );
        },
    );
});
