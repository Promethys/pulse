import { reactive } from 'vue';
import { vi } from 'vite-plus/test';

export const page = reactive({
    url: '/',
    props: {} as Record<string, unknown>,
});

export const http = {
    submit: vi.fn(),
};

export function inertiaModuleMock<T extends object>(actual: T): T {
    return {
        ...actual,
        usePage: () => page,
        useHttp: () => http,
    };
}

export function resetInertiaMocks(): void {
    page.url = '/';
    page.props = {};
    http.submit.mockReset();
}
