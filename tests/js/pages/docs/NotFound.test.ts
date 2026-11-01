import { render, screen } from '@testing-library/vue';
import { expect, it } from 'vite-plus/test';
import NotFound from '@/pages/docs/NotFound.vue';
import { index } from '@/routes/docs';
import { page } from '../../support/inertia';

it('offers docs home and search for missing documentation', () => {
    page.props = { auth: { user: null }, canRegister: false };
    render(NotFound);
    expect(
        screen.getByRole('heading', {
            name: 'This page moved or never existed',
        }),
    ).toBeVisible();
    expect(screen.getByRole('link', { name: 'Docs home' })).toHaveAttribute(
        'href',
        index.url(),
    );
    expect(
        screen.getByRole('link', { name: 'Search the docs' }),
    ).toHaveAttribute('href', `${index.url()}#search`);
});
