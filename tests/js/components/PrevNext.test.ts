import { render, screen } from '@testing-library/vue';
import { expect, it } from 'vite-plus/test';
import PrevNext from '@/components/pulse/PrevNext.vue';

const entry = {
    section: 'delivery',
    sectionTitle: 'Delivery',
    slug: 'discord',
    title: 'Discord',
    summary: '',
    url: '/docs/delivery/discord',
};

it.each([
    [null, entry, 'Next'],
    [entry, null, 'Previous'],
] as const)('handles the ends of the article order', (prev, next, label) => {
    render(PrevNext, { props: { prev, next } });
    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(screen.getByRole('link')).toHaveTextContent(label);
    expect(screen.getByRole('link')).toHaveAttribute('href', entry.url);
});

it('omits navigation when there are no neighbors', () => {
    render(PrevNext, { props: { prev: null, next: null } });
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
});
