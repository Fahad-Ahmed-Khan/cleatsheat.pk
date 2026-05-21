<script setup>
import StoreSectionHeader from '@/Components/Store/StoreSectionHeader.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    categories: { type: Array, default: () => [] },
});
</script>

<template>
    <section v-if="categories.length" class="store-section border-b border-stadium-outline-soft/20 bg-stadium-white py-10 md:py-14">
        <div class="store-container">
            <StoreSectionHeader
                title="Shop by category"
                subtitle="Tap a lane — filter by size, brand, and surface."
            />
            <div class="no-scrollbar -mx-4 mt-6 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                <template v-for="c in categories" :key="c.id">
                    <Link
                        v-if="c.slug"
                        :href="route('store.category', c.slug)"
                        class="flex min-h-11 shrink-0 items-center rounded-xl border border-stadium-outline-soft/50 bg-stadium-muted px-5 py-2.5 text-sm font-semibold text-stadium-ink shadow-sm transition hover:border-stadium-outline hover:bg-stadium-white active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-store-primary"
                    >
                        {{ c.name }}
                    </Link>
                </template>
                <template
                    v-for="ch in categories.flatMap((x) => x.children || []).filter((row) => row.slug).slice(0, 8)"
                    :key="'ch-' + ch.id"
                >
                    <Link
                        :href="route('store.category', ch.slug)"
                        class="flex min-h-11 shrink-0 items-center rounded-xl border border-stadium-outline-soft/40 bg-stadium-white px-5 py-2.5 text-sm font-medium text-stadium-secondary transition hover:border-stadium-outline focus-visible:ring-2 focus-visible:ring-store-primary"
                    >
                        {{ ch.name }}
                    </Link>
                </template>
            </div>
        </div>
    </section>
</template>
