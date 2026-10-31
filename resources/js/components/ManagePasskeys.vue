<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import type { Passkey } from '@/types/auth';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <header>
            <div class="mb-0.5 flex items-center gap-1">
                <h2 class="text-base font-medium">Passkeys</h2>
                <HelpTip
                    label="Passkeys"
                    text="A way to sign in using your device or password manager instead of typing a password."
                />
            </div>
            <p class="text-sm text-muted-foreground">
                Manage your passkeys for passwordless sign-in
            </p>
        </header>

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <KeyRound class="h-7 w-7 text-muted-foreground" />
                </div>
                <p class="font-medium">No passkeys yet</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Add a passkey to sign in without a password
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
