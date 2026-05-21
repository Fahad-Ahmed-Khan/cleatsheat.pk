<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    posts: { type: Object, required: true },
    seo: { type: Object, required: true },
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-14">
            <h1 class="text-display-md text-stadium-ink">Journal</h1>
            <p class="mt-3 text-body-lg text-stadium-secondary">
                Fit guides, sizing explainers, and style notes for shopping football boots online in Pakistan.
            </p>

            <ul class="mt-12 space-y-6">
                <li
                    v-for="p in posts.data"
                    :key="p.slug"
                    class="rounded-2xl border border-stadium-outline-soft/40 bg-stadium-white p-6 shadow-stadium ring-1 ring-stadium-outline-soft/30 transition hover:border-store-primary/30 hover:shadow-stadium-lg"
                >
                    <p
                        v-if="p.pillar_keyword"
                        class="text-label text-stadium-olive"
                    >
                        {{ p.pillar_keyword }}
                    </p>
                    <Link
                        :href="route('store.journal.show', p.slug)"
                        class="mt-2 block font-display text-xl font-bold leading-snug text-stadium-ink hover:text-stadium-olive"
                    >
                        {{ p.title }}
                    </Link>
                    <p v-if="p.excerpt" class="mt-3 text-sm leading-relaxed text-stadium-secondary">
                        {{ p.excerpt }}
                    </p>
                    <Link
                        :href="route('store.journal.show', p.slug)"
                        class="mt-4 inline-flex text-sm font-bold text-stadium-olive hover:underline"
                    >
                        Read article →
                    </Link>
                </li>
            </ul>

            <div
                v-if="posts.last_page > 1"
                class="mt-10 flex items-center justify-center gap-4 rounded-2xl bg-stadium-muted px-4 py-3 text-sm font-semibold"
            >
                <Link
                    v-if="posts.prev_page_url"
                    :href="posts.prev_page_url"
                    class="text-stadium-ink hover:text-stadium-olive hover:underline"
                    preserve-scroll
                >
                    Newer
                </Link>
                <span class="tabular-nums text-stadium-secondary">{{ posts.current_page }} / {{ posts.last_page }}</span>
                <Link
                    v-if="posts.next_page_url"
                    :href="posts.next_page_url"
                    class="text-stadium-ink hover:text-stadium-olive hover:underline"
                    preserve-scroll
                >
                    Older
                </Link>
            </div>
        </div>
    </StoreLayout>
</template>
