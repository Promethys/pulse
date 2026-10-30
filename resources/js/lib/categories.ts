const WORDS: Record<string, string> = {
    ai: 'AI',
    api: 'API',
    apis: 'APIs',
    devops: 'DevOps',
    php: 'PHP',
    sql: 'SQL',
};

export function categoryLabel(category: string): string {
    const label = category
        .split('-')
        .map((word) => WORDS[word] ?? word)
        .join(' ');

    return `${label.charAt(0).toUpperCase()}${label.slice(1)}`;
}
