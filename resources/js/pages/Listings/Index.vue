<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import Pagination from '../../components/Pagination.vue';

const props = defineProps({
    listings: { type: Object, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const processing = ref(false);
const saved = ref(false);
const saveForm = useForm({});

function clearSaveFeedback() {
    saveForm.clearErrors();
    saved.value = false;
}

function saveSearch() {
    clearSaveFeedback();
    saveForm.transform(() => form.value).post('/saved-searches', {
        preserveScroll: true,
        onSuccess: () => (saved.value = true),
    });
}

// Seeded from the server so the filter form survives a refresh, a shared link
// or the back button — the query string is the single source of truth.
const form = ref({
    property_type: props.filters.property_type ?? '',
    region: props.filters.region ?? '',
    min_bedrooms: props.filters.min_bedrooms ?? '',
    max_price: props.filters.max_price ?? '',
});

function search() {
    clearSaveFeedback();

    // Blank fields are dropped rather than sent empty, so the URL stays clean.
    const query = Object.fromEntries(
        Object.entries(form.value).filter(([, value]) => value !== '' && value !== null),
    );

    router.get('/', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // Inertia cancels the in-flight visit when a new one starts, so rapid
        // searches can't land out of order.
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function reset() {
    form.value = { property_type: '', region: '', min_bedrooms: '', max_price: '' };
    search();
}
</script>

<template>
    <Head title="Properties for sale" />

    <AppLayout heading="Properties for sale" subheading="Across our branches.">
        <ListingFilters
            v-model="form"
            :branches="branches"
            :property-types="propertyTypes"
            :processing="processing"
            @update:model-value="clearSaveFeedback"
            @submit="search"
            @reset="reset"
        >
            <template #actions>
                <button
                    type="button"
                    :disabled="saveForm.processing"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm disabled:opacity-50"
                    @click="saveSearch"
                >
                    {{ saveForm.processing ? 'Saving…' : 'Save search' }}
                </button>
            </template>
        </ListingFilters>

        <div class="mb-6" aria-live="polite">
            <p v-if="saved" class="mt-2 text-sm text-green-700">Search saved.</p>
            <p v-for="(error, field) in saveForm.errors" :key="field" class="mt-2 text-sm text-red-700">{{ error }}</p>
        </div>

        <p class="mb-4 text-sm text-slate-500" aria-live="polite">
            <span v-if="processing">Loading…</span>
            <span v-else>{{ listings.meta.total }} listing(s) found</span>
        </p>

        <div
            v-if="listings.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            :class="processing && 'opacity-50 transition'"
        >
            <ListingCard
                v-for="listing in listings.data"
                :key="listing.id"
                :listing="listing"
            />
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No listings match those filters.
        </p>

        <Pagination :meta="listings.meta" :links="listings.links" />
    </AppLayout>
</template>
