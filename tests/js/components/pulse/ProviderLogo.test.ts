import { render } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import ProviderLogo from '@/components/pulse/ProviderLogo.vue';

describe('ProviderLogo', () => {
    it.each(['gemini', 'anthropic', 'openai', 'groq', 'openrouter', 'zai'])(
        'draws a decorative monochrome logo for %s',
        (provider) => {
            const { container } = render(ProviderLogo, { props: { provider } });
            const svg = container.querySelector('svg');

            expect(svg).toHaveAttribute('aria-hidden', 'true');
            expect(svg).toHaveAttribute('fill', 'currentColor');
            expect(svg?.querySelectorAll('path').length).toBeGreaterThan(0);
        },
    );

    it('draws nothing for an unknown provider', () => {
        const { container } = render(ProviderLogo, {
            props: { provider: 'unknown' },
        });

        expect(container.querySelector('svg')).toBeNull();
    });
});
