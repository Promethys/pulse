<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ProviderMark from '@/components/pulse/ProviderMark.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ConnectedProvider } from '@/types';

const props = defineProps<{ connectedProviders: ConnectedProvider[] }>();
const page = usePage();
const providers = computed(() => page.props.socialProviders ?? []);
const disconnecting = ref(false);
const lastSignInMethod = computed(
    () =>
        !page.props.auth.user.has_password &&
        props.connectedProviders.filter((account) =>
            providers.value.some((provider) => provider === account.provider),
        ).length <= 1,
);

function isConnected(provider: string): boolean {
    return props.connectedProviders.some(
        (account) => account.provider === provider,
    );
}

function disconnect(provider: string) {
    disconnecting.value = true;
    router.delete(`/auth/${provider}/unlink`, {
        preserveScroll: true,
        onFinish: () => (disconnecting.value = false),
    });
}
</script>

<template>
    <Card v-if="providers.length" class="mb-4 shadow-none">
        <CardHeader>
            <CardTitle class="font-display">Connected accounts</CardTitle>
            <CardDescription>
                Connect Google or GitHub to sign in to your Pulse account.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <InputError :message="page.props.errors?.social" />
            <div
                v-for="provider in providers"
                :key="provider"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div class="space-y-1">
                    <p class="flex items-center gap-2 font-medium">
                        <ProviderMark :provider="provider" />
                        {{ provider === 'google' ? 'Google' : 'GitHub' }}
                    </p>
                    <p
                        v-if="isConnected(provider) && lastSignInMethod"
                        class="text-sm text-muted-foreground"
                    >
                        Set a password or connect another account before
                        disconnecting your last sign-in method.
                    </p>
                </div>
                <Button
                    v-if="isConnected(provider)"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/10"
                    :disabled="lastSignInMethod || disconnecting"
                    @click="disconnect(provider)"
                >
                    Disconnect
                </Button>
                <Button v-else as-child variant="outline" size="sm">
                    <a :href="`/auth/${provider}/redirect?intent=link`"
                        >Connect</a
                    >
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
