<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description: 'Enter one of your emergency recovery codes.',
            buttonText: 'Use an authentication code instead',
        };
    }

    return {
        title: 'Two-factor check',
        description: 'Enter the 6-digit code from your authenticator app.',
        buttonText: 'Use a recovery code instead',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-4">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                    class="size-11 text-lg"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="w-full focus-ring"
                    :disabled="processing"
                    >Continue</Button
                >
                <div class="text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 focus-ring transition-colors duration-300 ease-out hover:decoration-current! active:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Label for="recovery_code">Recovery code</Label>
                <Input
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    v-focus
                    required
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    class="w-full focus-ring"
                    :disabled="processing"
                    >Continue</Button
                >

                <div class="text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 focus-ring transition-colors duration-300 ease-out hover:decoration-current! active:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
