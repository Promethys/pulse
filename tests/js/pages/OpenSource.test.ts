import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import OpenSource from '@/pages/OpenSource.vue';
import { page } from '../support/inertia';

describe('OpenSource', () => {
    it.each([
        [true, false],
        [false, false],
        [true, true],
        [false, true],
    ])('respects registration=%s and signed in=%s', (canRegister, signedIn) => {
        page.props = {
            auth: { user: signedIn ? { id: 1 } : null },
            canRegister,
        };
        render(OpenSource);
        expect(
            screen.queryAllByRole('link', { name: 'Create an account' }),
        ).toHaveLength(canRegister && !signedIn ? 1 : 0);
        expect(
            screen.queryAllByRole('link', { name: 'Open Pulse' }),
        ).toHaveLength(signedIn ? 1 : 0);
        expect(screen.queryAllByRole('link', { name: 'Log in' })).toHaveLength(
            signedIn ? 0 : 1,
        );
    });

    it('links the repository, setup, contribution guide and license', () => {
        page.props = { auth: { user: null }, canRegister: true };
        const { container } = render(OpenSource);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Open source' }),
        ).toBeVisible();
        for (const [name, suffix] of [
            ['Promethys/pulse on GitHub', ''],
            ['README', '/blob/main/README.md'],
            ['Read the guide', '/blob/main/CONTRIBUTING.md'],
            ['LICENSE', '/blob/main/LICENSE'],
            ['Open an issue', '/issues'],
            ['Suggest a feature', '/issues'],
        ]) {
            const link = screen.getByRole('link', { name });
            expect(link).toHaveAttribute(
                'href',
                `https://github.com/Promethys/pulse${suffix}`,
            );
            expect(link).toHaveAttribute('rel', 'noreferrer');
        }
        expect(container).toHaveTextContent('MIT license');
        expect(container).toHaveTextContent(
            'Laravel, Vue 3, Inertia and PostgreSQL',
        );
        expect(container).not.toHaveTextContent('docker compose up -d');
    });
});
