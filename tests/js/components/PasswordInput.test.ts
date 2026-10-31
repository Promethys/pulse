import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';

// PasswordInput exposes its own $el, which Testing Library can't unwrap: render it inside a parent.
function renderInput(props: Record<string, unknown> = {}) {
    return render(
        defineComponent({
            render: () => h('div', [h(PasswordInput, props)]),
        }),
    );
}

describe('PasswordInput', () => {
    it('hides the password by default', () => {
        renderInput({ placeholder: 'Password' });

        expect(screen.getByPlaceholderText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Show password' }),
        ).toBeVisible();
    });

    it('toggles the password visibility', async () => {
        const user = userEvent.setup();
        renderInput({ placeholder: 'Password' });
        const input = screen.getByPlaceholderText('Password');

        await user.click(screen.getByRole('button', { name: 'Show password' }));
        expect(input).toHaveAttribute('type', 'text');

        await user.click(screen.getByRole('button', { name: 'Hide password' }));
        expect(input).toHaveAttribute('type', 'password');
    });

    it('keeps the typed value while toggling', async () => {
        const user = userEvent.setup();
        renderInput({ placeholder: 'Password' });
        const input = screen.getByPlaceholderText('Password');

        await user.type(input, 's3cret');
        await user.click(screen.getByRole('button', { name: 'Show password' }));

        expect(input).toHaveValue('s3cret');
    });

    it('passes attributes and classes to the input, not the wrapper', () => {
        const { container } = renderInput({
            class: 'w-64',
            id: 'password',
            name: 'password',
            placeholder: 'Password',
        });
        const input = screen.getByPlaceholderText('Password');

        expect(input).toHaveAttribute('id', 'password');
        expect(input).toHaveAttribute('name', 'password');
        expect(input).toHaveClass('w-64', 'pr-10');
        expect(container.querySelector('.relative')).not.toHaveAttribute('id');
    });

    it('never submits the surrounding form when toggling', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn((event: Event) => event.preventDefault());
        render(
            defineComponent({
                render: () =>
                    h('form', { onSubmit }, [
                        h(PasswordInput, { placeholder: 'Password' }),
                    ]),
            }),
        );

        await user.click(screen.getByRole('button', { name: 'Show password' }));

        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('keeps the toggle out of the tab order', () => {
        renderInput();

        expect(
            screen.getByRole('button', { name: 'Show password' }),
        ).toHaveAttribute('tabindex', '-1');
    });

    // mount, not render: Testing Library moves the nodes after mounting, which drops the focus
    it.each([
        [true, true],
        [false, false],
    ])('autofocus %j focuses the input: %j', (autofocus, focused) => {
        const wrapper = mount(PasswordInput, {
            props: { autofocus },
            attachTo: document.body,
        });
        const input = wrapper.find('input').element;

        expect(document.activeElement === input).toBe(focused);
        wrapper.unmount();
    });

    it('leaves focus alone without the autofocus prop', () => {
        const wrapper = mount(PasswordInput, { attachTo: document.body });

        expect(document.activeElement).not.toBe(wrapper.find('input').element);
        wrapper.unmount();
    });

    it('exposes focus() to parents', () => {
        const input = ref<InstanceType<typeof PasswordInput> | null>(null);
        const wrapper = mount(
            defineComponent({
                render: () =>
                    h(PasswordInput, { ref: input, placeholder: 'Password' }),
            }),
            { attachTo: document.body },
        );

        input.value?.focus();

        expect(document.activeElement).toBe(wrapper.find('input').element);
        wrapper.unmount();
    });
});
