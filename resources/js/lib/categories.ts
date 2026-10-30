export function categoryLabel(category: string): string {
    const label = category.replaceAll('-', ' ');

    return label.startsWith('ai ') || label === 'ai'
        ? `AI${label.slice(2)}`
        : `${label.charAt(0).toUpperCase()}${label.slice(1)}`;
}
