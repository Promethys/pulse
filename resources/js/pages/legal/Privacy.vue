<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import LegalLayout from '@/components/pulse/LegalLayout.vue';

defineOptions({ layout: [] });
const page = usePage<{ supportEmail: string }>();

const lastUpdated = 'November 1, 2026';
const sections = [
    { id: 'what-pulse-stores', label: 'What Pulse stores' },
    { id: 'your-ai-keys', label: 'Your AI keys' },
    { id: 'what-goes-to-ai', label: 'What goes to AI providers' },
    { id: 'services-involved', label: 'Services involved' },
    { id: 'cookies', label: 'Cookies and browser storage' },
    { id: 'retention', label: 'How long data is kept' },
    { id: 'deleting-your-account', label: 'Deleting your account' },
    { id: 'your-rights', label: 'Your choices and rights' },
    { id: 'contact', label: 'Contact' },
];
</script>

<template>
    <Head title="Privacy">
        <meta
            name="description"
            content="What Pulse stores, what goes to AI providers and delivery services, and what happens when you delete your account."
        />
    </Head>
    <LegalLayout
        title="Privacy"
        :last-updated="lastUpdated"
        intro="Pulse uses your settings and sources to prepare your daily digests. This page explains the data it handles."
        :sections="sections"
    >
        <template #what-pulse-stores>
            <p>
                Pulse stores your name, email address, account timestamps and
                sign-in information. Passwords are hashed. If you enable
                two-factor authentication or passkeys, it also stores the
                information needed to use them, including recovery codes and
                passkey credentials.
            </p>
            <p>
                It stores your profile text, interests, source subscriptions,
                custom sources, timezone, digest times and limits, AI provider
                settings and keys, and delivery destinations. Connected Discord
                servers have their names and server IDs saved. Connected
                newsletter inboxes have their access credentials saved.
            </p>
            <p>
                Pulse saves collected source titles, excerpts and links,
                generated news and learning posts, topic history, delivery
                status and error details. Database sessions include your IP
                address and browser user agent, session data and last activity.
            </p>
        </template>
        <template #your-ai-keys>
            <p>
                AI keys and delivery settings are encrypted in the database.
                Pulse saves your AI provider, model, key order, consent date and
                the last four characters of each key separately. Your profile
                and digests are not encrypted by these field protections.
            </p>
            <p>
                Keys authenticate requests to the relevant provider, including
                digest generation and connection tests. You can remove an AI key
                in settings, or revoke it with your provider.
            </p>
        </template>
        <template #what-goes-to-ai>
            <p>
                For news, Pulse sends your profile text, interests, item limit,
                existing tags and candidate source titles, excerpts and links.
                This can include newsletter excerpts from a connected inbox. For
                learning posts, it sends your profile, the chosen topic and
                category, existing tags and recent tip titles to avoid repeats.
            </p>
            <p>
                Requests use the providers configured with your keys, with
                fallback to another configured provider when supported. If your
                account has access to instance AI and has no keys of its own,
                requests use the instance's configured providers. Each provider
                handles requests under its own terms and privacy policy.
                Anything personal you put in your profile or source content can
                therefore reach those providers.
            </p>
        </template>
        <template #services-involved>
            <ul>
                <li>AI providers process the requests described above.</li>
                <li>
                    The configured mail service handles recipient addresses and
                    messages for account mail, verification and email digests.
                </li>
                <li>
                    Discord receives digest content and destination details if
                    you connect a Discord destination.
                </li>
                <li>
                    Resend provides access to newsletter messages if you connect
                    a receiving inbox. Source websites receive requests when
                    Pulse fetches feeds or checks links.
                </li>
                <li>
                    Our hosting provider runs the app and stores its data. Our
                    network provider handles traffic to and from the service.
                </li>
            </ul>
            <p>
                Application logs may contain technical data such as account IDs,
                source URLs and error messages used to diagnose problems.
            </p>
        </template>
        <template #cookies>
            <p>
                Pulse uses a session cookie for your visit and sign-in, and a
                CSRF token cookie to protect requests. If you choose to stay
                signed in, a remember-me cookie supports that choice. The
                <code>appearance</code> cookie saves your theme, and
                <code>sidebar_state</code> saves whether the sidebar is open.
                Your theme is also saved in your browser's local storage.
            </p>
            <p>
                Pulse has no analytics or advertising trackers. You can clear
                cookies and local storage in your browser; doing so can sign you
                out and reset these preferences.
            </p>
        </template>
        <template #retention>
            <p>
                Account settings, keys, saved digests and their delivery history
                are kept until you remove the relevant data or delete your
                account. Saved digests have no automatic age-based expiry.
            </p>
            <p>
                Collected source items are shared records. The scheduled digest
                task removes items older than 30 days only when no saved post
                references them. Referenced items remain while those references
                exist. Sessions expire after the configured idle period, which
                defaults to two hours; expired database rows are cleaned up
                periodically during requests, rather than immediately at expiry.
            </p>
            <p>
                Log retention depends on the configured logging destination.
                Account deletion does not automatically erase logs.
            </p>
        </template>
        <template #deleting-your-account>
            <p>
                You can delete your account in profile settings. This removes
                your account, profile and digest settings, interest selections
                and source subscriptions, custom sources and their credentials,
                AI keys, delivery destinations and history, connected server
                records, passkeys, saved posts and learning topic history.
            </p>
            <p>
                Your sessions and password reset records are deleted too. Shared
                source items follow the retention rule above, including items
                collected from your custom sources.
            </p>
            <p>
                Posts already sent to Discord or email stay there. Deleting your
                Pulse account does not delete copies held by recipients or
                third-party services.
            </p>
        </template>
        <template #your-rights>
            <p>
                You can update your profile and settings, remove keys and
                destinations, or delete your account. There is no built-in data
                export. You can ask through the contact link below for a copy of
                your data or a correction.
            </p>
            <p>
                Depending on where you live, you may have rights to access,
                correct or delete your data.
            </p>
        </template>
        <template #contact>
            <p>
                Email
                <a :href="`mailto:${page.props.supportEmail}`">{{
                    page.props.supportEmail
                }}</a>
                with questions about privacy or a request about your data.
            </p>
        </template>
    </LegalLayout>
</template>
