<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <nav class="flex gap-8">
                <Link
                    href="/"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    Street Listings
                </Link>
                <Link
                    href="/saved-searches"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    Saved searches
                </Link>
                <Link
                    href="/alerts"
                    class="text-sm font-medium text-slate-500 hover:text-slate-900"
                >
                    Alerts
                </Link>
            </nav>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-slate-500">{{ subheading }}</p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
