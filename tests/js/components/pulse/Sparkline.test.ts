import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Sparkline from '@/components/pulse/Sparkline.vue';

function coordinates(container: Element): [number, number][] {
    const points = container.querySelector('polyline')?.getAttribute('points');
    if (!points) throw new Error('Expected a polyline with points');

    return points
        .split(/\s+/)
        .filter(Boolean)
        .map((point): [number, number] => {
            const [x, y] = point.split(',').map(Number);
            if (x === undefined || y === undefined) {
                throw new Error('Expected an x,y coordinate pair');
            }
            return [x, y];
        });
}

describe('Sparkline', () => {
    it('exposes an accessible image label', () => {
        render(Sparkline, { props: { data: [1, 2, 3] } });
        expect(
            screen.getByRole('img', {
                name: 'Items per week, last 12 weeks',
            }),
        ).toBeVisible();
    });

    it('draws one point per week in a single polyline', () => {
        const view = render(Sparkline, {
            props: { data: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] },
        });
        expect(view.container.querySelectorAll('polyline')).toHaveLength(1);
        expect(coordinates(view.container)).toHaveLength(12);
    });

    it('draws an ascending series rising from first to last', () => {
        const view = render(Sparkline, {
            props: { data: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] },
        });
        const points = coordinates(view.container);
        const first = points.at(0);
        const last = points.at(-1);
        if (!first || !last) throw new Error('Expected first and last points');
        expect(last[1]).toBeLessThan(first[1]);
    });

    it('draws twelve zeros as a finite flat line', () => {
        const view = render(Sparkline, {
            props: { data: Array.from({ length: 12 }, () => 0) },
        });
        const points = coordinates(view.container);
        expect(points).toHaveLength(12);
        expect(new Set(points.map((point) => point[1])).size).toBe(1);
        expect(points.flat().every(Number.isFinite)).toBe(true);
    });

    it('draws a single value with finite coordinates', () => {
        const view = render(Sparkline, { props: { data: [3] } });
        const points = coordinates(view.container);
        expect(points).toHaveLength(1);
        expect(points.flat().every(Number.isFinite)).toBe(true);
    });
});
