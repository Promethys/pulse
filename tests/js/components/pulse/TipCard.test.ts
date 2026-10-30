import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import TipCard from '@/components/pulse/TipCard.vue';
import { learningPostProps } from '../../support/dayProps';

describe('TipCard', () => {
    it('keeps the title plain and clamped with its full text available', () => {
        const tip = learningPostProps('tip');
        render(TipCard, { props: { tip } });
        const title = screen.getByRole('heading', { name: tip.title });
        expect(title).toHaveClass('line-clamp-2');
        expect(title).toHaveAttribute('title', tip.title);
        expect(title.closest('a')).toBeNull();
        expect(screen.getByText('Programming')).toBeVisible();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('renders server HTML as prose including formatting, code, lists and links', () => {
        render(TipCard, { props: { tip: learningPostProps('tip') } });
        expect(screen.getByText('EXPLAIN').tagName).toBe('STRONG');
        expect(screen.getByText('SELECT').tagName).toBe('CODE');
        expect(
            screen.getByText('SELECT 1;').closest('pre'),
        ).toBeInTheDocument();
        expect(screen.getByRole('listitem')).toHaveTextContent('Measure first');
        const link = screen.getByRole('link', { name: 'Query plan guide' });
        expect(link).toHaveAttribute('href', 'https://docs.example.test/plans');
        expect(link.closest('.pulse-prose')).toHaveClass('line-clamp-6');
    });

    it('accepts no tags without inventing a chip', () => {
        const tip = learningPostProps('tip');
        tip.tags = [];
        render(TipCard, { props: { tip } });
        expect(screen.queryByText('Programming')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: tip.title })).toBeVisible();
    });

    it('shows the full title once the card is expanded', async () => {
        vi.spyOn(HTMLElement.prototype, 'scrollHeight', 'get').mockReturnValue(
            300,
        );
        vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(
            120,
        );
        const user = userEvent.setup();
        render(TipCard, { props: { tip: learningPostProps() } });
        const title = screen.getByRole('heading', { level: 3 });

        expect(title).toHaveClass('line-clamp-2');

        await user.click(
            await screen.findByRole('button', { name: 'Read more' }),
        );

        expect(title).not.toHaveClass('line-clamp-2');
    });
});
