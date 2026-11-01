<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { ref } from 'vue';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import ThemeToggle from '@/components/pulse/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { features, home, login, openSource, register } from '@/routes';
import { index as docs } from '@/routes/docs';

const page = usePage<{ canRegister: boolean }>();
const isCurrent = (url: string) =>
    page.url === url || page.url.startsWith(`${url}/`);
const open = ref(false);
const navigation = [
    { label: 'Features', href: features() },
    { label: 'Docs', href: docs() },
    { label: 'Open source', href: openSource() },
];
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur"
    >
        <div
            class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 py-3"
        >
            <Link :href="home()" class="inline-flex items-center"
                ><PulseLogo
            /></Link>
            <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
                <Button
                    v-for="item in navigation"
                    :key="item.label"
                    variant="ghost"
                    :class="{ 'bg-accent/40': isCurrent(item.href.url) }"
                    as-child
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            isCurrent(item.href.url) ? 'page' : undefined
                        "
                        >{{ item.label }}</Link
                    >
                </Button>
            </nav>
            <div class="flex items-center gap-1">
                <ThemeToggle />
                <div class="hidden items-center gap-1 md:flex">
                    <Button v-if="page.props.auth.user" as-child>
                        <Link :href="home()">Open Pulse</Link>
                    </Button>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button v-if="page.props.canRegister" as-child>
                            <Link :href="register()">Create an account</Link>
                        </Button>
                    </template>
                </div>
                <Sheet v-model:open="open">
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="md:hidden"
                            aria-label="Open menu"
                        >
                            <Menu aria-hidden="true" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="right"
                        class="w-72 p-6"
                        :aria-describedby="undefined"
                    >
                        <SheetTitle class="sr-only">Menu</SheetTitle>
                        <nav
                            class="mt-8 flex flex-col gap-1"
                            aria-label="Mobile"
                        >
                            <Button
                                v-for="item in navigation"
                                :key="item.label"
                                variant="ghost"
                                class="justify-start"
                                as-child
                            >
                                <Link :href="item.href" @click="open = false">
                                    {{ item.label }}
                                </Link>
                            </Button>
                            <div class="my-3 h-px bg-border" />
                            <div class="flex flex-col gap-2">
                                <Button
                                    v-if="page.props.auth.user"
                                    class="w-full"
                                    as-child
                                >
                                    <Link :href="home()" @click="open = false">
                                        Open Pulse
                                    </Link>
                                </Button>
                                <template v-else>
                                    <Button
                                        variant="ghost"
                                        class="w-full justify-start"
                                        as-child
                                    >
                                        <Link
                                            :href="login()"
                                            @click="open = false"
                                        >
                                            Log in
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="page.props.canRegister"
                                        class="w-full"
                                        as-child
                                    >
                                        <Link
                                            :href="register()"
                                            @click="open = false"
                                        >
                                            Create an account
                                        </Link>
                                    </Button>
                                </template>
                            </div>
                        </nav>
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </header>
</template>
