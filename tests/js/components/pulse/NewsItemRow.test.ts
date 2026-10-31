import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import { newsItemProps } from '../../support/dayProps';

describe('NewsItemRow', () => {
    it('links the clamped title and Open source to the original URL in a new tab', () => {
        const item = newsItemProps();
        render(NewsItemRow, { props: { item } });
        const title = screen.getByRole('heading', { name: item.title });
        expect(title).toHaveClass('line-clamp-2');
        expect(title).toHaveAttribute('title', item.title);
        for (const link of [
            screen.getByRole('link', { name: new RegExp(item.title) }),
            screen.getByRole('link', { name: /^Open source$/ }),
        ]) {
            expect(link).toHaveAttribute('href', item.url);
            expect(link).toHaveAttribute('target', '_blank');
            expect(link).toHaveAttribute('rel', 'noreferrer');
        }
        expect(screen.getByText(item.summary!)).toBeVisible();
        expect(screen.getByText(item.source!)).toBeVisible();
        expect(screen.getByText('Programming')).toBeVisible();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('omits the summary paragraph when summary is null', () => {
        const item = newsItemProps();
        item.summary = null;
        const view = render(NewsItemRow, { props: { item } });
        expect(screen.getByRole('link', { name: item.title })).toBeVisible();
        expect(view.container.querySelector('p')).not.toBeInTheDocument();
    });

    it('omits a missing source without a placeholder', () => {
        const item = newsItemProps();
        item.source = null;
        render(NewsItemRow, { props: { item } });
        expect(screen.queryByText('Compiler News')).not.toBeInTheDocument();
        expect(
            screen.queryByText(/unknown source|null|undefined/i),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /^Open source$/ }),
        ).toHaveAttribute('href', item.url);
    });

    it('accepts an item with no tags', () => {
        const item = newsItemProps();
        item.tags = [];
        render(NewsItemRow, { props: { item } });
        expect(screen.queryByText('Programming')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: item.title })).toHaveAttribute(
            'href',
            item.url,
        );
    });
});

describe('NewsItemRow search date', () => {
    it('shows the digest date after the source when requested', () => {
        render(NewsItemRow, {
            props: { item: newsItemProps(), showDate: true },
        });
        expect(screen.getByText('28 Oct')).toBeVisible();
        expect(
            screen.getByText('Compiler News').parentElement,
        ).toHaveTextContent(/Compiler News\s*·\s*28 Oct/);
    });
});

describe('tag links', () => {
    it('links the news item tag to its page', () => {
        render(NewsItemRow, { props: { item: newsItemProps() } });
        expect(
            screen.getByRole('link', { name: 'Programming' }),
        ).toHaveAttribute('href', '/tags/programming');
    });
});
