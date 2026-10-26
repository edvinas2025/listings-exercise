<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import Pagination from '../../components/Pagination.vue';
import { formatPrice } from '../../format';

defineProps({
    savedSearches: { type: Object, required: true },
});

const deleting = ref(null);

function summary(search) {
    const { criteria } = search;
    const parts = [];

    if (criteria.region !== null) parts.push(criteria.region);
    if (criteria.property_type !== null) parts.push(search.property_type_label);
    if (criteria.max_price !== null) parts.push(`Up to ${formatPrice(criteria.max_price)}`);
    if (criteria.min_bedrooms !== null) parts.push(`${criteria.min_bedrooms}+ bedrooms`);

    return parts.join(' · ') || 'All properties';
}

function remove(search) {
    router.delete(`/saved-searches/${search.id}`, {
        preserveScroll: true,
        onStart: () => (deleting.value = search.id),
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="Your saved criteria for new properties.">
        <ul v-if="savedSearches.data.length" class="space-y-3">
            <li
                v-for="search in savedSearches.data"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4"
            >
                <Link :href="search.listing_url" class="font-medium text-slate-900 underline-offset-2 hover:underline">
                    {{ summary(search) }}
                </Link>
                <button
                    type="button"
                    :disabled="deleting !== null"
                    :aria-label="`Delete saved search: ${summary(search)}`"
                    class="rounded-lg px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                    @click="remove(search)"
                >
                    {{ deleting === search.id ? 'Deleting…' : 'Delete' }}
                </button>
            </li>
        </ul>

        <p v-else class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
            No saved searches yet.
            <Link href="/" class="text-slate-900 underline">Find properties</Link> and save your search.
        </p>

        <Pagination :meta="savedSearches.meta" :links="savedSearches.links" />
    </AppLayout>
</template>
