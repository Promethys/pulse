<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({ layout: [] });

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Forgot your password?"
        description="Enter your email and we'll send you a reset link."
    >
        <Head title="Forgot password" />

        <div
            v-if="status"
            role="status"
            class="rounded-lg bg-success/15 p-2 text-center text-sm text-success"
        >
            {{ status }}
        </div>

        <div class="space-y-4">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        v-focus
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button
                        class="w-full"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Email me a reset link
                    </Button>
                </div>
            </Form>
        </div>

        <template #footer>
            <TextLink :href="login()" class="hover:underline"
                >← Back to log in</TextLink
            >
        </template>
    </AuthLayout>
</template>
