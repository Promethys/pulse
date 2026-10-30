import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import TopicCard from '@/components/pulse/TopicCard.vue';
import { learningPostProps } from '../../support/dayProps';

describe('TopicCard', () => {
    it('keeps the title plain and clamped with its full text available', () => {
        const topic = learningPostProps('topic');
        render(TopicCard, { props: { topic } });
        const title = screen.getByRole('heading', { name: topic.title });
        expect(title).toHaveClass('line-clamp-1');
        expect(title).toHaveAttribute('title', topic.title);
        expect(title.closest('a')).toBeNull();
        expect(screen.getByText('Programming')).toBeVisible();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('renders server HTML as prose including formatting, code, lists and links', () => {
        render(TopicCard, { props: { topic: learningPostProps('topic') } });
        expect(screen.getByText('EXPLAIN').tagName).toBe('STRONG');
        expect(screen.getByText('SELECT').tagName).toBe('CODE');
        expect(
            screen.getByText('SELECT 1;').closest('pre'),
        ).toBeInTheDocument();
        expect(screen.getByRole('listitem')).toHaveTextContent('Measure first');
        const link = screen.getByRole('link', { name: 'Query plan guide' });
        expect(link).toHaveAttribute('href', 'https://docs.example.test/plans');
        expect(link.closest('.pulse-prose')).toBeInTheDocument();
        expect(link.closest('.pulse-prose')).toHaveClass('line-clamp-6');
    });

    it('accepts no tags without inventing a chip', () => {
        const topic = learningPostProps('topic');
        topic.tags = [];
        render(TopicCard, { props: { topic } });
        expect(screen.queryByText('Programming')).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: topic.title }),
        ).toBeVisible();
    });

    it('shows the category in the topic eyebrow', () => {
        render(TopicCard, { props: { topic: learningPostProps('topic') } });
        expect(screen.getByText(/^Topic\s*·\s*Databases$/)).toBeVisible();
    });

    it('omits the category and separator when category is null', () => {
        const topic = learningPostProps('topic');
        topic.category = null;
        render(TopicCard, { props: { topic } });
        expect(screen.getByText(/^Topic$/)).toBeVisible();
        expect(
            screen.queryByText(/Databases|Topic\s*·|null|undefined/),
        ).not.toBeInTheDocument();
    });
});
