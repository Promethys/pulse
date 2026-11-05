import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import VerifyEmail from '@/pages/auth/VerifyEmail.vue';

describe('VerifyEmail', () => {
    it.each([undefined, 'verification-link-sent'])(
        'keeps the email quota notice below resend with status=%s',
        (status) => {
            render(VerifyEmail, { props: { status } });

            const notice = screen.getByRole('note');
            expect(notice).toBeVisible();
            expect(notice).toHaveTextContent(
                "This instance can only send a few emails a day. If the link doesn't arrive, your account will be verified by hand.",
            );
            expect(notice).toHaveClass('text-sm', 'text-muted-foreground');
            expect(
                screen
                    .getByRole('button', { name: 'Resend the email' })
                    .compareDocumentPosition(notice) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
            ).toBeTruthy();
            expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        },
    );
});
