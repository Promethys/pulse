import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import SocialLoginButtons from '@/components/pulse/SocialLoginButtons.vue';
import { page } from '../../support/inertia';

describe('SocialLoginButtons', () => {
    it.each([
        { providers: ['google'], labels: ['Google'] },
        { providers: ['github'], labels: ['GitHub'] },
        { providers: ['google', 'github'], labels: ['Google', 'GitHub'] },
    ])(
        'shows only the enabled providers: $providers',
        ({ providers, labels }) => {
            page.props.socialProviders = providers;
            render(SocialLoginButtons);

            expect(screen.getAllByRole('link')).toHaveLength(providers.length);
            expect(screen.getByText(/^or continue with$/i)).toBeVisible();

            for (const label of ['Google', 'GitHub']) {
                const link = screen.queryByRole('link', {
                    name: `Continue with ${label}`,
                });

                if (labels.includes(label)) {
                    expect(link).toBeVisible();
                } else {
                    expect(link).not.toBeInTheDocument();
                }
            }
        },
    );

    it('renders nothing, including no divider, when no providers are enabled', () => {
        page.props.socialProviders = [];
        const { container } = render(SocialLoginButtons);

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(
            screen.queryByText(/^or continue with$/i),
        ).not.toBeInTheDocument();
        expect(container).toBeEmptyDOMElement();
    });

    it('uses ordinary anchors to the provider redirect endpoints', () => {
        page.props.socialProviders = ['google', 'github'];
        const wrapper = mount(SocialLoginButtons);

        expect(wrapper.findAllComponents(Link)).toHaveLength(0);

        for (const [provider, label] of [
            ['google', 'Google'],
            ['github', 'GitHub'],
        ]) {
            const link = wrapper.get(
                `a[href="/auth/${provider}/redirect"]`,
            ).element;

            expect(link).toHaveTextContent(`Continue with ${label}`);
            expect(link.tagName).toBe('A');
            expect(link).toHaveAttribute('href', `/auth/${provider}/redirect`);
            expect(link).not.toHaveAttribute('target', '_blank');
        }

        expect(wrapper.findAll('a')).toHaveLength(2);
        wrapper.unmount();
    });
});
