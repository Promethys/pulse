import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import AiKeyForm from '@/components/pulse/AiKeyForm.vue';
import { test as testAiKey } from '@/routes/onboarding/ai-key';
import { http } from '../../support/inertia';
import { onboardingProps } from '../../support/onboardingProps';

function renderForm() {
    const providers = onboardingProps().providers;
    const value = ref({
        provider: 'gemini',
        model: providers[0].suggested_models[0],
        api_key: '',
        consent: false,
    });
    render(
        defineComponent({
            setup: () => () =>
                h(AiKeyForm, {
                    providers,
                    modelValue: value.value,
                    'onUpdate:modelValue': (next: typeof value.value) => {
                        value.value = next;
                    },
                }),
        }),
    );
    return value;
}

async function chooseModel(
    user: ReturnType<typeof userEvent.setup>,
    name: string,
) {
    const select = screen.getByRole('combobox', { name: /^Model$/ });
    if (select instanceof HTMLSelectElement) {
        await user.selectOptions(select, name);
    } else {
        await user.click(select);
        await user.click(await screen.findByRole('option', { name }));
    }
}

describe('AiKeyForm', () => {
    it('explains why a key is needed and links every provider without price or free-tier claims', () => {
        renderForm();

        expect(screen.getByText('Why your own key?')).toBeVisible();
        const providers = onboardingProps().providers;
        expect(screen.getAllByRole('link')).toHaveLength(providers.length);
        providers.forEach((provider) => {
            expect(
                screen.getByRole('link', {
                    name: `Get a ${provider.label} key`,
                }),
            ).toHaveAttribute('href', provider.key_url);
        });
        expect(
            screen.queryByText(
                /\btokens?\b|\bfree\b|\$\s*\d|€\s*\d|\d+\s*€|per month/i,
            ),
        ).not.toBeInTheDocument();
    });

    it.each(onboardingProps().providers)(
        'selects $label and resets the model to its first suggestion',
        async (provider) => {
            const value = renderForm();
            const user = userEvent.setup();
            const group = screen.getByRole('radiogroup', { name: 'Provider' });
            expect(within(group).getAllByRole('radio')).toHaveLength(6);
            expect(
                within(group).getByRole('radio', { name: /^Gemini$/ }),
            ).toBeChecked();
            await user.click(
                within(group).getByRole('radio', { name: provider.label }),
            );

            expect(
                within(group).getByRole('radio', { name: provider.label }),
            ).toBeChecked();
            expect(
                within(group)
                    .getAllByRole('radio')
                    .filter(
                        (radio) =>
                            radio.getAttribute('aria-checked') === 'true',
                    ),
            ).toHaveLength(1);
            expect(value.value.provider).toBe(provider.value);
            expect(value.value.model).toBe(provider.suggested_models[0]);
            expect(
                screen.getByRole('combobox', { name: 'Model' }),
            ).toHaveTextContent(provider.suggested_models[0]);
        },
    );

    it('offers all suggested models and stores free text for Other model', async () => {
        const value = renderForm();
        const user = userEvent.setup();
        await user.click(screen.getByRole('radio', { name: 'Anthropic' }));
        await chooseModel(user, 'claude-sonnet-5-5');
        expect(value.value.model).toBe('claude-sonnet-5-5');
        await chooseModel(user, 'Other model');
        const custom = screen.getByRole('textbox', {
            name: /Other model|Model name/i,
        });
        await user.type(custom, 'custom-model-v2');
        expect(value.value.model).toBe('custom-model-v2');

        await user.click(screen.getByRole('radio', { name: 'Z.AI' }));
        expect(value.value.model).toBe('glm-4.7-flash');
        expect(
            screen.queryByRole('textbox', { name: /Other model|Model name/i }),
        ).not.toBeInTheDocument();
    });

    it('masks a typed key, toggles visibility without losing it and records consent', async () => {
        const value = renderForm();
        const user = userEvent.setup();
        const key = screen.getByLabelText('Gemini API key');
        expect(key).toHaveAttribute('type', 'password');
        await user.type(key, 'fake-key-for-tests');
        await user.click(
            screen.getByRole('button', { name: /^Show(?: password| key)?$/i }),
        );
        expect(key).toHaveAttribute('type', 'text');
        expect(key).toHaveValue('fake-key-for-tests');
        await user.click(
            screen.getByRole('button', { name: /^Hide(?: password| key)?$/i }),
        );
        expect(key).toHaveAttribute('type', 'password');
        const consent = screen.getByRole('checkbox', {
            name: /stored encrypted.*own digests/i,
        });
        expect(consent).not.toBeChecked();
        await user.click(consent);
        expect(value.value).toMatchObject({
            api_key: 'fake-key-for-tests',
            consent: true,
        });
    });

    it('disables testing for empty or whitespace-only keys without requiring consent to test', async () => {
        renderForm();
        const user = userEvent.setup();
        const button = screen.getByRole('button', { name: 'Test the key' });
        expect(button).toBeDisabled();
        await user.type(screen.getByLabelText('Gemini API key'), '   ');
        expect(button).toBeDisabled();
        expect(http.submit).not.toHaveBeenCalled();
        await user.type(screen.getByLabelText('Gemini API key'), 'fake-key');
        expect(button).toBeEnabled();
    });

    it.each([
        { ok: true, message: 'Key works.' },
        { ok: false, message: 'Provider rejected the key (401 Unauthorized).' },
    ])('shows loading and the JSON result: $message', async (result) => {
        let resolve!: (value: typeof result) => void;
        http.submit.mockReturnValueOnce(
            new Promise<typeof result>((done) => {
                resolve = done;
            }),
        );
        renderForm();
        const user = userEvent.setup();
        await user.type(screen.getByLabelText('Gemini API key'), 'fake-key');
        await user.click(screen.getByRole('button', { name: 'Test the key' }));

        expect(http.submit).toHaveBeenCalledOnce();
        const [first, second] = http.submit.mock.calls[0];
        const endpoint =
            typeof first === 'string' ? { method: first, url: second } : first;
        expect(endpoint).toMatchObject(testAiKey());
        expect(screen.getByRole('button', { name: /Testing/ })).toBeDisabled();
        resolve(result);

        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent(
                result.message,
            ),
        );
        expect(
            screen.getByRole('button', { name: 'Test the key' }),
        ).toBeEnabled();
    });

    it('recovers from a rejected request and allows another test', async () => {
        http.submit.mockRejectedValueOnce(new Error('Network unavailable.'));
        http.submit.mockResolvedValueOnce({ ok: true, message: 'Key works.' });
        renderForm();
        const user = userEvent.setup();
        await user.type(screen.getByLabelText('Gemini API key'), 'fake-key');
        await user.click(screen.getByRole('button', { name: 'Test the key' }));
        expect(await screen.findByRole('status')).toHaveTextContent(
            /Network unavailable|could not|try again/i,
        );
        await user.click(screen.getByRole('button', { name: 'Test the key' }));
        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent('Key works.'),
        );
        expect(http.submit).toHaveBeenCalledTimes(2);
    });
});
