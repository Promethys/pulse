import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';
import { http } from '../support/inertia';

type Responses = Partial<Record<'qr' | 'key' | 'codes', unknown>>;

function respond(responses: Responses): void {
    http.submit.mockImplementation(async (route: { url: string }) => {
        const key =
            route.url === qrCode().url
                ? 'qr'
                : route.url === secretKey().url
                  ? 'key'
                  : route.url === recoveryCodes().url
                    ? 'codes'
                    : null;

        if (key === null || !(key in responses)) {
            throw new Error('request failed');
        }

        return responses[key];
    });
}

describe('useTwoFactorAuth', () => {
    beforeEach(() => {
        useTwoFactorAuth().clearTwoFactorAuthData();
    });

    it('starts empty', () => {
        const auth = useTwoFactorAuth();

        expect(auth.qrCodeSvg.value).toBeNull();
        expect(auth.manualSetupKey.value).toBeNull();
        expect(auth.recoveryCodesList.value).toEqual([]);
        expect(auth.errors.value).toEqual([]);
        expect(auth.hasSetupData.value).toBe(false);
    });

    it('fetches the QR code from its route', async () => {
        respond({ qr: { svg: '<svg/>', url: 'otpauth://x' } });
        const auth = useTwoFactorAuth();

        await auth.fetchQrCode();

        expect(http.submit).toHaveBeenCalledWith(qrCode());
        expect(auth.qrCodeSvg.value).toBe('<svg/>');
    });

    it('reports a failed QR code request', async () => {
        respond({});
        const auth = useTwoFactorAuth();

        await auth.fetchQrCode();

        expect(auth.qrCodeSvg.value).toBeNull();
        expect(auth.errors.value).toEqual(['Failed to fetch QR code']);
    });

    it('fetches the manual setup key', async () => {
        respond({ key: { secretKey: 'ABCD1234' } });
        const auth = useTwoFactorAuth();

        await auth.fetchSetupKey();

        expect(http.submit).toHaveBeenCalledWith(secretKey());
        expect(auth.manualSetupKey.value).toBe('ABCD1234');
    });

    it('reports a failed setup key request', async () => {
        respond({});
        const auth = useTwoFactorAuth();

        await auth.fetchSetupKey();

        expect(auth.manualSetupKey.value).toBeNull();
        expect(auth.errors.value).toEqual(['Failed to fetch a setup key']);
    });

    it('has setup data only with both the QR code and the key', async () => {
        respond({ qr: { svg: '<svg/>' }, key: { secretKey: 'ABCD1234' } });
        const auth = useTwoFactorAuth();

        await auth.fetchQrCode();
        expect(auth.hasSetupData.value).toBe(false);

        await auth.fetchSetupKey();
        expect(auth.hasSetupData.value).toBe(true);
    });

    it('fetches all setup data after clearing old errors', async () => {
        const auth = useTwoFactorAuth();
        auth.errors.value = ['old error'];
        respond({ qr: { svg: '<svg/>' }, key: { secretKey: 'ABCD1234' } });

        await auth.fetchSetupData();

        expect(auth.errors.value).toEqual([]);
        expect(auth.hasSetupData.value).toBe(true);
    });

    it('keeps the part that worked when setup data partly fails', async () => {
        respond({ qr: { svg: '<svg/>' } });
        const auth = useTwoFactorAuth();

        await auth.fetchSetupData();

        expect(auth.qrCodeSvg.value).toBe('<svg/>');
        expect(auth.hasSetupData.value).toBe(false);
        expect(auth.errors.value).toEqual(['Failed to fetch a setup key']);
    });

    it('fetches recovery codes after clearing old errors', async () => {
        const auth = useTwoFactorAuth();
        auth.errors.value = ['old error'];
        respond({ codes: ['code-1', 'code-2'] });

        await auth.fetchRecoveryCodes();

        expect(http.submit).toHaveBeenCalledWith(recoveryCodes());
        expect(auth.recoveryCodesList.value).toEqual(['code-1', 'code-2']);
        expect(auth.errors.value).toEqual([]);
    });

    it('empties recovery codes when the request fails', async () => {
        const auth = useTwoFactorAuth();
        auth.recoveryCodesList.value = ['stale'];
        respond({});

        await auth.fetchRecoveryCodes();

        expect(auth.recoveryCodesList.value).toEqual([]);
        expect(auth.errors.value).toEqual(['Failed to fetch recovery codes']);
    });

    it('clears setup data and errors but keeps recovery codes', async () => {
        respond({
            qr: { svg: '<svg/>' },
            key: { secretKey: 'ABCD1234' },
            codes: ['code-1'],
        });
        const auth = useTwoFactorAuth();
        await auth.fetchSetupData();
        await auth.fetchRecoveryCodes();
        auth.errors.value = ['error'];

        auth.clearSetupData();

        expect(auth.qrCodeSvg.value).toBeNull();
        expect(auth.manualSetupKey.value).toBeNull();
        expect(auth.errors.value).toEqual([]);
        expect(auth.recoveryCodesList.value).toEqual(['code-1']);
    });

    it('clears everything with clearTwoFactorAuthData', async () => {
        respond({ codes: ['code-1'] });
        const auth = useTwoFactorAuth();
        await auth.fetchRecoveryCodes();

        auth.clearTwoFactorAuthData();

        expect(auth.recoveryCodesList.value).toEqual([]);
    });

    it('shares its state between components', async () => {
        respond({ codes: ['code-1'] });

        await useTwoFactorAuth().fetchRecoveryCodes();

        expect(useTwoFactorAuth().recoveryCodesList.value).toEqual(['code-1']);
    });
});
