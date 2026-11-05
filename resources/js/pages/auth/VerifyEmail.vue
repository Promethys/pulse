<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Check your inbox',
        description:
            'We sent you a link. Click it to confirm your email address.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div class="flex justify-center">
        <span
            class="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground"
        >
            <MailCheck class="size-6" aria-hidden="true" />
        </span>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="rounded-lg bg-success/15 p-2 text-center text-sm text-success"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-4 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="outline" class="w-full">
            <Spinner v-if="processing" />
            Resend the email
        </Button>

        <p role="note" class="text-sm text-muted-foreground">
            This instance can only send a few emails a day. If the link doesn't
            arrive, your account will be verified by hand.
        </p>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
