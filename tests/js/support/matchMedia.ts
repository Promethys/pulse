type ChangeListener = (event: MediaQueryListEvent) => void;

const listeners = new Set<ChangeListener>();
let prefersDark = false;

export function mockMatchMedia(dark = false): void {
    prefersDark = dark;
    listeners.clear();

    window.matchMedia = (query: string) =>
        ({
            get matches() {
                return query === '(prefers-color-scheme: dark)' && prefersDark;
            },
            media: query,
            onchange: null,
            addEventListener: (_type: string, listener: ChangeListener) =>
                listeners.add(listener),
            removeEventListener: (_type: string, listener: ChangeListener) =>
                listeners.delete(listener),
            addListener: () => undefined,
            removeListener: () => undefined,
            dispatchEvent: () => true,
        }) as unknown as MediaQueryList;
}

export function changeSystemTheme(dark: boolean): void {
    prefersDark = dark;
    listeners.forEach((listener) =>
        listener({ matches: dark } as MediaQueryListEvent),
    );
}

export function systemThemeListenerCount(): number {
    return listeners.size;
}
