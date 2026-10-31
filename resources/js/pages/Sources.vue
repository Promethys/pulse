<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Rss, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CategoryGroup from '@/components/pulse/CategoryGroup.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import SourceFormDialog from '@/components/pulse/SourceFormDialog.vue';
import SourceRow from '@/components/pulse/SourceRow.vue';
import SuggestInterestDialog from '@/components/pulse/SuggestInterestDialog.vue';
import SuggestSourceDialog from '@/components/pulse/SuggestSourceDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { ManagedSource, SourcesProps } from '@/types';

const props = defineProps<SourcesProps>();
const page = usePage();
const tab = ref(
    new URL(page.url, 'https://pulse.test').searchParams.get('tab') === 'mine'
        ? 'mine'
        : 'interest',
);
const formOpen = ref(false);
const editing = ref<ManagedSource | null>(null);
const deleting = ref<ManagedSource | null>(null);
const deletingOpen = ref(false);
const deleteProcessing = ref(false);
const interestsOpen = ref(false);
const suggestInterestOpen = ref(false);
const suggestSourceOpen = ref(false);
const suggesting = ref<ManagedSource | null>(null);
const selected = ref<string[]>([]);
const interestsProcessing = ref(false);
const interestErrors = ref<Record<string, string>>({});
const sourceErrors = ref<Record<string, string>>({});
const subtitle = computed(
    () =>
        `${props.counts.total} ${props.counts.total === 1 ? 'source' : 'sources'} · ${props.counts.failing} failing`,
);
function openForm(source: ManagedSource | null = null) {
    editing.value = source;
    sourceErrors.value = {};
    formOpen.value = true;
}
function openInterests() {
    selected.value = props.interests
        .filter((interest) => interest.selected && interest.status === 'live')
        .map((interest) => interest.slug);
    interestErrors.value = {};
    interestsOpen.value = true;
}
function toggleInterest(slug: string) {
    if (
        !props.interests.some(
            (interest) => interest.slug === slug && interest.status === 'live',
        )
    )
        return;
    selected.value = selected.value.includes(slug)
        ? selected.value.filter((value) => value !== slug)
        : [...selected.value, slug];
}
function saveInterests() {
    if (!selected.value.length || interestsProcessing.value) return;
    interestsProcessing.value = true;
    router.put(
        '/sources/interests',
        { interests: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                interestsOpen.value = false;
            },
            onError: (errors) => {
                interestErrors.value = errors;
            },
            onFinish: () => {
                interestsProcessing.value = false;
            },
        },
    );
}
function confirmDelete(source: ManagedSource) {
    deleting.value = source;
    deletingOpen.value = true;
}
function deleteSource() {
    if (!deleting.value || deleteProcessing.value) return;
    deleteProcessing.value = true;
    router.delete(`/sources/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deletingOpen.value = false;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Sources" />
    <div>
        <PageHeader title="Sources" :subtitle="subtitle" />
        <Tabs v-model="tab">
            <TabsList class="mb-4 w-full md:w-auto">
                <TabsTrigger value="interest">By interest</TabsTrigger>
                <TabsTrigger value="mine"
                    >My sources
                    <span class="text-muted-foreground">{{
                        custom.length
                    }}</span></TabsTrigger
                >
            </TabsList>
            <TabsContent value="interest" class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="openInterests"
                        >Add or remove interests</Button
                    >
                    <Button
                        variant="outline"
                        @click="suggestInterestOpen = true"
                        >Suggest an interest</Button
                    >
                </div>
                <section v-for="group in catalog" :key="group.interest.slug">
                    <h2 class="mb-2 text-lg font-semibold">
                        {{ group.interest.name }}
                    </h2>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
                    >
                        <SourceRow
                            v-for="source in group.sources"
                            :key="source.id"
                            :source="source"
                        />
                    </ul>
                </section>
            </TabsContent>
            <TabsContent value="mine" class="space-y-4">
                <Button @click="openForm()"
                    ><Plus aria-hidden="true" /> Add a source</Button
                >
                <EmptyState
                    v-if="!custom.length"
                    title="No sources of your own yet"
                    description="Add a blog feed or connect a newsletter inbox."
                >
                    <template #icon
                        ><Rss class="size-5" aria-hidden="true"
                    /></template>
                </EmptyState>
                <ul
                    v-else
                    class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
                >
                    <SourceRow
                        v-for="source in custom"
                        :key="source.id"
                        :source="source"
                    >
                        <template #actions>
                            <Button
                                v-if="source.type === 'feed'"
                                variant="ghost"
                                size="sm"
                                :disabled="source.suggested"
                                @click="
                                    suggesting = source;
                                    suggestSourceOpen = true;
                                "
                                ><Send aria-hidden="true" />{{
                                    source.suggested
                                        ? 'Suggested'
                                        : 'Suggest for the catalog'
                                }}</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="openForm(source)"
                                ><Pencil aria-hidden="true" /> Edit</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                                @click="confirmDelete(source)"
                                ><Trash2 aria-hidden="true" /> Delete</Button
                            >
                        </template>
                    </SourceRow>
                </ul>
            </TabsContent>
        </Tabs>
        <SuggestSourceDialog
            v-model:open="suggestSourceOpen"
            :source="suggesting"
            :interests="interests"
        />
        <SuggestInterestDialog
            v-model:open="suggestInterestOpen"
            :categories="categories"
        />
        <SourceFormDialog
            v-model:open="formOpen"
            :source="editing"
            :interests="interests"
            :errors="sourceErrors"
            @errors="sourceErrors = $event"
        />
        <Dialog v-model:open="interestsOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Add or remove interests</DialogTitle>
                    <DialogDescription
                        >Pick at least one interest for your
                        digest.</DialogDescription
                    >
                </DialogHeader>
                <div class="space-y-7">
                    <CategoryGroup
                        v-for="category in categories"
                        :key="category.value"
                        :category="category"
                        :interests="
                            interests.filter(
                                (interest) =>
                                    interest.category === category.value,
                            )
                        "
                        :selected="selected"
                        @toggle="toggleInterest"
                    />
                </div>
                <InputError
                    v-for="(error, field) in interestErrors"
                    :key="field"
                    :message="error"
                />
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="interestsProcessing"
                        @click="interestsOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="!selected.length || interestsProcessing"
                        @click="saveInterests"
                        >Save</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="deletingOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription
                        >This removes the source from your digests. Past digests
                        stay.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="deleteProcessing"
                        @click="deletingOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="deleteProcessing"
                        @click="deleteSource"
                        >Delete source</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
