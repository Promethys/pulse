<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

const props = defineProps<Props>();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? '/dashboard');
    },
});
</script>

<template>
    <div v-if="isSupported">
        <div class="grid gap-2">
            <div class="flex items-center gap-2">
                <p class="text-sm text-muted-foreground">
                    Use a passkey you previously added to Pulse.
                </p>
                <HelpTip
                    label="Passkeys"
                    text="A way to sign in using your device or password manager instead of typing a password."
                />
            </div>
            <Button
                type="button"
                variant="outline"
                class="w-full focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent"
                @click="verify"
                :disabled="isLoading"
            >
                <Spinner v-if="isLoading" />
                <KeyRound v-else class="h-4 w-4" />
                {{
                    isLoading
                        ? (props.loadingLabel ?? 'Authenticating...')
                        : (props.label ?? 'Sign in with a passkey')
                }}
            </Button>

            <div v-if="error" class="text-center">
                <InputError :message="error" />
            </div>
        </div>

        <div class="my-6 flex items-center gap-2">
            <Separator class="flex-1" />
            <span class="shrink-0 text-xs text-muted-foreground uppercase">
                {{ props.separator ?? 'Or continue with email' }}
            </span>
            <Separator class="flex-1" />
        </div>
    </div>
</template>
