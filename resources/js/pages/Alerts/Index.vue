<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New properties matching your saved searches.">
        <ul v-if="alerts.data.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="alert in alerts.data" :key="alert.id">
                <p class="mb-2 text-xs text-slate-500">
                    New match · <time :datetime="alert.created_at">{{ formatDate(alert.created_at) }}</time>
                </p>
                <ListingCard v-if="alert.listing" :listing="alert.listing" />
                <p v-else class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                    This property is no longer available.
                </p>
            </li>
        </ul>

        <p v-else class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
            No alerts yet. New matching properties will appear here when they go live.
            <Link href="/saved-searches" class="text-slate-900 underline">Manage saved searches</Link>.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
