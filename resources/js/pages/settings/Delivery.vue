<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { Bell, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import DestinationRow from '@/components/pulse/DestinationRow.vue';
import DiscordIcon from '@/components/pulse/DiscordIcon.vue';
import ReceivesPicker from '@/components/pulse/ReceivesPicker.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { KeyTestResult } from '@/types/onboarding';
import type {
    DeliveryDestination,
    DeliveryGuild,
    DeliveryProps,
} from '@/types/settings';

const props = defineProps<DeliveryProps>();
const adding = ref(false);
const guild = ref<DeliveryGuild | null>(null);
const disconnecting = ref<DeliveryGuild | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const updateErrors = ref<Record<string, string>>({});
const webhookUrls = ref<Record<number, string>>({});
const input = ref({
    type: 'discord_webhook',
    guild_id: '',
    channel_id: '',
    name: '',
    webhook_url: '',
    address: '',
    kinds: ['news'],
});
const http = useHttp<Record<string, never>, KeyTestResult>({});
const canAdd = computed(
    () =>
        !processing.value &&
        input.value.kinds.length &&
        (input.value.type === 'discord_bot'
            ? Boolean(input.value.channel_id)
            : input.value.type === 'email'
              ? Boolean(input.value.address.trim())
              : Boolean(
                    input.value.name.trim() && input.value.webhook_url.trim(),
                )),
);
function initials(name: string) {
    return name
        .split(/\s+/)
        .map((word) => word[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
function openAdd(type: string, server: DeliveryGuild | null = null) {
    guild.value = server;
    input.value = {
        type,
        guild_id: server?.guild_id ?? '',
        channel_id: server?.forums[0]?.id ?? '',
        name: '',
        webhook_url: '',
        address: props.account_email,
        kinds: ['news'],
    };
    errors.value = {};
    adding.value = true;
}
function save() {
    if (!canAdd.value) return;
    processing.value = true;
    const data = {
        type: input.value.type,
        kinds: [...input.value.kinds],
        ...(input.value.type === 'discord_bot'
            ? {
                  guild_id: input.value.guild_id,
                  channel_id: input.value.channel_id,
              }
            : input.value.type === 'email'
              ? { address: input.value.address }
              : {
                    name: input.value.name,
                    webhook_url: input.value.webhook_url,
                }),
    };
    router.post('/settings/delivery', data, {
        preserveScroll: true,
        onSuccess: () => {
            adding.value = false;
            input.value.webhook_url = '';
        },
        onError: (value) => {
            errors.value = value;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
function update(
    channel: DeliveryDestination,
    fields: {
        enabled?: boolean;
        kinds?: string[];
        channel_id?: string;
        name?: string;
        address?: string;
        webhook_url?: string;
    },
    onSuccess?: () => void,
) {
    updateErrors.value = {};
    router.put(
        `/settings/delivery/${channel.id}`,
        { enabled: channel.enabled, kinds: channel.kinds, ...fields },
        {
            preserveScroll: true,
            onSuccess,
            onError: (value) => {
                updateErrors.value = value;
            },
        },
    );
}
function updateText(
    channel: DeliveryDestination,
    field: 'name' | 'address' | 'webhook_url',
    event: FocusEvent,
) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (field === 'webhook_url' && !target.value.trim()) return;
    update(channel, { [field]: target.value }, () => {
        if (field === 'webhook_url') webhookUrls.value[channel.id] = '';
    });
}
function selectForum(channel: DeliveryDestination, value: unknown) {
    if (typeof value === 'string') update(channel, { channel_id: value });
}
async function test(channel: DeliveryDestination): Promise<KeyTestResult> {
    return await http.submit({
        method: 'post',
        url: `/settings/delivery/${channel.id}/test`,
    });
}
function remove(channel: DeliveryDestination) {
    router.delete(`/settings/delivery/${channel.id}`, { preserveScroll: true });
}
function disconnect() {
    if (!disconnecting.value) return;
    router.delete(`/settings/delivery/guilds/${disconnecting.value.id}`, {
        preserveScroll: true,
    });
    disconnecting.value = null;
}
</script>

<template>
    <Head title="Delivery settings" />
    <InputError
        v-for="(error, field) in updateErrors"
        :key="field"
        :message="error"
    />
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">Discord servers</CardTitle
            ><CardDescription
                >Pulse posts each digest as a thread in a forum
                channel.</CardDescription
            ></CardHeader
        >
        <CardContent class="space-y-4">
            <div
                v-for="server in guilds"
                :key="server.id"
                class="overflow-hidden rounded-xl border border-border"
            >
                <div class="flex items-center gap-3 bg-secondary p-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground"
                        >{{ initials(server.name) }}</span
                    >
                    <span class="min-w-0 flex-1 font-semibold">{{
                        server.name
                    }}</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                        @click="disconnecting = server"
                        >Disconnect</Button
                    >
                </div>
                <p
                    v-if="server.forums_error"
                    class="px-4 pt-3 text-sm text-destructive"
                >
                    Could not load forum channels. Try again later.
                </p>
                <div class="divide-y divide-border">
                    <DestinationRow
                        v-for="channel in server.destinations"
                        :key="channel.id"
                        :channel="channel"
                        :title="channel.name"
                        :on-test="() => test(channel)"
                        @update:enabled="update(channel, { enabled: $event })"
                        @delete="remove(channel)"
                    >
                        <div class="flex flex-wrap items-center gap-4">
                            <Select
                                :model-value="channel.target.channel_id ?? ''"
                                :disabled="server.forums_error"
                                @update:model-value="
                                    selectForum(channel, $event)
                                "
                            >
                                <SelectTrigger
                                    :aria-label="`Forum channel for ${channel.name}`"
                                    class="w-44 font-mono text-sm"
                                    ><SelectValue
                                        :placeholder="
                                            channel.target.channel_id ??
                                            'Forum channel'
                                        "
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="forum in server.forums"
                                        :key="forum.id"
                                        :value="forum.id"
                                        class="font-mono"
                                        >{{ forum.name }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                            <ReceivesPicker
                                :model-value="channel.kinds"
                                @update:model-value="
                                    update(channel, { kinds: $event })
                                "
                            />
                        </div>
                    </DestinationRow>
                </div>
                <div class="space-y-2 p-4">
                    <p
                        v-if="!server.forums_error && !server.forums.length"
                        class="text-sm text-muted-foreground"
                    >
                        No forum channels found in this server. Forum channels
                        need a Community server: enable Community in the
                        server's settings, create a channel of type Forum, then
                        refresh this page. You can also use a webhook below.
                    </p>
                    <Button
                        variant="outline"
                        :disabled="server.forums_error || !server.forums.length"
                        @click="openAdd('discord_bot', server)"
                        ><Plus aria-hidden="true" />Add a forum channel</Button
                    >
                </div>
            </div>
            <Button v-if="install_url" as-child
                ><a :href="install_url"
                    ><DiscordIcon class="size-4" />Add to Discord</a
                ></Button
            >
            <p v-else class="text-sm text-muted-foreground">
                Discord installation is not configured.
            </p>
        </CardContent>
    </Card>
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">Discord webhooks</CardTitle
            ><CardDescription
                >For servers where you can't add the bot.</CardDescription
            ></CardHeader
        >
        <CardContent class="space-y-4">
            <div
                v-if="webhooks.length"
                class="divide-y divide-border overflow-hidden rounded-xl border border-border"
            >
                <DestinationRow
                    v-for="channel in webhooks"
                    :key="channel.id"
                    :channel="channel"
                    :title="channel.name"
                    :subtitle="channel.target.webhook ?? ''"
                    :on-test="() => test(channel)"
                    @update:enabled="update(channel, { enabled: $event })"
                    @delete="remove(channel)"
                >
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="space-y-1.5">
                            <Label :for="`webhook-name-${channel.id}`"
                                >Name</Label
                            ><Input
                                :id="`webhook-name-${channel.id}`"
                                :model-value="channel.name"
                                maxlength="100"
                                @blur="updateText(channel, 'name', $event)"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label :for="`webhook-url-${channel.id}`"
                                >Replace webhook URL</Label
                            ><Input
                                :id="`webhook-url-${channel.id}`"
                                v-model="webhookUrls[channel.id]"
                                type="password"
                                autocomplete="off"
                                placeholder="Keep the current URL"
                                @blur="
                                    updateText(channel, 'webhook_url', $event)
                                "
                            />
                        </div>
                        <ReceivesPicker
                            :model-value="channel.kinds"
                            @update:model-value="
                                update(channel, { kinds: $event })
                            "
                        />
                    </div>
                </DestinationRow>
            </div>
            <Button variant="outline" @click="openAdd('discord_webhook')"
                ><Plus aria-hidden="true" />Add webhook</Button
            >
        </CardContent>
    </Card>
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">Email</CardTitle
            ><CardDescription
                >Receive your digests by email.</CardDescription
            ></CardHeader
        >
        <CardContent>
            <div
                v-if="email"
                class="overflow-hidden rounded-xl border border-border"
            >
                <DestinationRow
                    :channel="email"
                    :title="email.name"
                    :subtitle="email.target.address ?? ''"
                    :on-test="() => test(email!)"
                    @update:enabled="update(email, { enabled: $event })"
                    @delete="remove(email)"
                >
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <Label for="delivery-email">Email address</Label
                            ><Input
                                id="delivery-email"
                                type="email"
                                :model-value="email.target.address ?? ''"
                                @blur="updateText(email, 'address', $event)"
                            />
                        </div>
                        <ReceivesPicker
                            :model-value="email.kinds"
                            @update:model-value="
                                update(email, { kinds: $event })
                            "
                        />
                    </div>
                </DestinationRow>
            </div>
            <Button v-else variant="outline" @click="openAdd('email')"
                ><Plus aria-hidden="true" />Add email</Button
            >
        </CardContent>
    </Card>
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">In the app</CardTitle></CardHeader
        >
        <CardContent
            ><p class="flex items-center gap-2 text-sm text-muted-foreground">
                <Bell class="size-4" aria-hidden="true" />Always on. Every
                digest appears on your Today page.
            </p></CardContent
        >
    </Card>
    <Dialog v-model:open="adding" @update:open="input.webhook_url = ''">
        <DialogContent>
            <DialogHeader
                ><DialogTitle class="font-display">{{
                    input.type === 'discord_bot'
                        ? 'Add a forum channel'
                        : input.type === 'email'
                          ? 'Add email'
                          : 'Add webhook'
                }}</DialogTitle
                ><DialogDescription
                    >Choose where Pulse sends your digests.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="save">
                <div v-if="input.type === 'discord_bot'" class="space-y-1.5">
                    <Label for="add-forum">Forum channel</Label>
                    <Select v-model="input.channel_id"
                        ><SelectTrigger id="add-forum" class="w-full"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="forum in guild?.forums ?? []"
                                :key="forum.id"
                                :value="forum.id"
                                >{{ forum.name }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                </div>
                <template v-else-if="input.type === 'discord_webhook'">
                    <div class="space-y-1.5">
                        <Label for="add-name">Name</Label
                        ><Input
                            id="add-name"
                            v-model="input.name"
                            maxlength="100"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="add-webhook">Webhook URL</Label
                        ><Input
                            id="add-webhook"
                            v-model="input.webhook_url"
                            type="password"
                            autocomplete="off"
                        />
                    </div>
                </template>
                <div v-else class="space-y-1.5">
                    <Label for="add-email">Email address</Label
                    ><Input
                        id="add-email"
                        v-model="input.address"
                        type="email"
                    />
                </div>
                <ReceivesPicker v-model="input.kinds" />
                <InputError
                    v-for="(error, field) in errors"
                    :key="field"
                    :message="error"
                />
                <Button :disabled="!canAdd">Add</Button>
            </form>
        </DialogContent>
    </Dialog>
    <AlertDialog
        :open="disconnecting !== null"
        @update:open="!$event && (disconnecting = null)"
    >
        <AlertDialogContent>
            <AlertDialogTitle class="font-display text-lg font-semibold"
                >Disconnect {{ disconnecting?.name }}?</AlertDialogTitle
            >
            <AlertDialogDescription class="text-sm text-muted-foreground"
                >This removes this server's destinations from Pulse. The bot
                will stay in the server.</AlertDialogDescription
            >
            <div class="flex justify-end gap-2">
                <AlertDialogCancel as-child
                    ><Button variant="outline"
                        >Cancel</Button
                    ></AlertDialogCancel
                >
                <Button
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                    @click="disconnect"
                    >Disconnect</Button
                >
            </div>
        </AlertDialogContent>
    </AlertDialog>
</template>
