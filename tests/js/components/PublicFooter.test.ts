import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import PublicFooter from '@/components/pulse/PublicFooter.vue';
import { features, home, openSource, privacy, terms } from '@/routes';
import { index as docs } from '@/routes/docs';
import { page } from '../support/inertia';

describe('PublicFooter', () => {
    it.each([
        ['guest', null, true],
        ['signed in', { id: 1 }, true],
        ['registration closed', null, false],
    ])(
        'keeps product, project and legal links available for %s',
        (_state, user, canRegister) => {
            page.props = { auth: { user }, canRegister };
            render(PublicFooter);

            for (const title of ['Product', 'Project', 'Legal']) {
                expect(screen.getByText(title)).toBeVisible();
            }
            for (const [name, href] of [
                ['Pulse', home.url()],
                ['Features', features.url()],
                ['Docs', docs.url()],
                ['Open source', openSource.url()],
                ['Privacy', privacy.url()],
                ['Terms', terms.url()],
            ]) {
                expect(screen.getByRole('link', { name })).toHaveAttribute(
                    'href',
                    href,
                );
            }
            for (const [name, suffix] of [
                ['GitHub', ''],
                ['Releases', '/releases'],
                ['Report a bug', '/issues'],
            ]) {
                const link = screen.getByRole('link', { name });
                expect(link).toHaveAttribute(
                    'href',
                    `https://github.com/Promethys/pulse${suffix}`,
                );
                expect(link).toHaveAttribute('target', '_blank');
                expect(link).toHaveAttribute('rel', 'noreferrer');
            }
            expect(
                screen.getByText('Free and open source, MIT license.'),
            ).toBeVisible();
            expect(
                screen.queryByRole('link', { name: 'Create an account' }),
            ).not.toBeInTheDocument();
        },
    );
});
