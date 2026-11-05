<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import LegalLayout from '@/components/pulse/LegalLayout.vue';
import { privacy } from '@/routes';

defineOptions({ layout: [] });
const page = usePage<{ supportEmail: string }>();

const lastUpdated = 'November 1, 2026';
const sections = [
    { id: 'what-pulse-is', label: 'What Pulse is' },
    { id: 'availability', label: 'A free service and availability' },
    { id: 'your-account-and-keys', label: 'Your account and your API keys' },
    { id: 'provider-costs', label: 'Provider costs' },
    { id: 'acceptable-use', label: 'Acceptable use' },
    { id: 'ai-summaries', label: 'AI summaries can be wrong' },
    { id: 'third-party-links', label: 'Third-party content and services' },
    { id: 'ending-your-account', label: 'Ending your account' },
    { id: 'changes', label: 'Changes to these terms' },
    { id: 'contact', label: 'Contact' },
];
</script>

<template>
    <Head title="Terms">
        <meta
            name="description"
            content="Terms for using Pulse: availability, your account and API keys, provider charges, acceptable use and ending your account."
        />
    </Head>
    <LegalLayout
        title="Terms of use"
        :last-updated="lastUpdated"
        intro="These terms apply when you use Pulse. Please read them before creating an account or connecting your services."
        :sections="sections"
    >
        <template #what-pulse-is>
            <p>
                Pulse prepares news digests from the sources you follow, plus
                learning posts when available for your interests. You can read
                posts in the app and configure delivery to email or Discord.
            </p>
            <p>
                The Pulse source code is covered by the MIT license. That
                license governs using, modifying and redistributing the code;
                these terms cover using this service.
            </p>
        </template>
        <template #availability>
            <p>
                Pulse is a free service, provided as is. We do not guarantee
                availability, uninterrupted access or successful delivery.
                Digests may be late, incomplete or missing. Features and the
                service itself may change or stop.
            </p>
        </template>
        <template #your-account-and-keys>
            <p>
                You are responsible for your account and your API keys. Keep
                your password, passkeys, recovery codes and keys secure. Only
                connect accounts, keys, inboxes and delivery destinations that
                you are allowed to use, and follow each provider's terms.
            </p>
            <p>
                If a key is exposed, revoke it with its provider and remove it
                from Pulse. Avoid putting sensitive information in your profile
                or sources, since that content can be sent to AI providers.
            </p>
        </template>
        <template #provider-costs>
            <p>
                Pulse does not charge for using this service. Your AI provider
                and other connected services may charge for usage, including
                requests made by Pulse. Those charges are yours to manage with
                the provider. Check its pricing and limits before adding a key;
                free tiers can change or run out.
            </p>
        </template>
        <template #acceptable-use>
            <p>
                Use Pulse lawfully and respect other people's privacy and
                rights. Do not access accounts or data without permission,
                disrupt the service, bypass security or usage limits, send spam,
                or abuse connected services. We may suspend accounts that abuse
                the service or break these terms.
            </p>
        </template>
        <template #ai-summaries>
            <p>
                AI summaries can be wrong. Generated news and learning posts may
                omit context, misread a source or invent details. Check original
                sources and verify anything important before acting on it. You
                are responsible for how you use the output.
            </p>
        </template>
        <template #third-party-links>
            <p>
                Pulse links to third-party articles and uses external AI, mail,
                source and delivery services. We do not control their content,
                availability, pricing or data practices. Their own terms and
                privacy policies apply when you use them.
            </p>
        </template>
        <template #ending-your-account>
            <p>
                You can delete your account from profile settings at any time.
                Copies already delivered to email or Discord remain there, and
                shared source items follow Pulse's retention rules. Read the
                <Link :href="privacy()" class="focus-ring">privacy page</Link>
                for the details of account deletion.
            </p>
        </template>
        <template #changes>
            <p>
                We may update these terms. The current version will be posted
                here with its last updated date. Review it when that date
                changes. Continuing to use Pulse after an update means you
                accept the updated terms.
            </p>
        </template>
        <template #contact>
            <p>
                Email
                <a
                    :href="`mailto:${page.props.supportEmail}`"
                    class="focus-ring"
                    >{{ page.props.supportEmail }}</a
                >
                with questions about these terms.
            </p>
        </template>
    </LegalLayout>
</template>
