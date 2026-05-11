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
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">
                Journal
            </h1>
            <p class="mt-3 text-sm leading-relaxed text-stone-600">
                Fit guides, sizing explainers, and style notes for shopping shoes online in Pakistan.
            </p>

            <ul class="mt-12 space-y-8">
                <li v-for="p in posts.data" :key="p.slug" class="border-b border-stone-200 pb-8">
                    <p v-if="p.pillar_keyword" class="text-[11px] font-semibold uppercase tracking-widest text-emerald-700">
                        {{ p.pillar_keyword }}
                    </p>
                    <Link
                        :href="route('store.journal.show', p.slug)"
                        class="mt-2 block text-xl font-semibold text-stone-900 hover:underline"
                    >
                        {{ p.title }}
                    </Link>
                    <p v-if="p.excerpt" class="mt-2 text-sm leading-relaxed text-stone-600">
                        {{ p.excerpt }}
                    </p>
                </li>
            </ul>

            <div v-if="posts.last_page > 1" class="mt-10 flex justify-center gap-4 text-sm font-medium">
                <Link
                    v-if="posts.prev_page_url"
                    :href="posts.prev_page_url"
                    class="text-stone-700 underline-offset-2 hover:underline"
                    preserve-scroll
                >
                    Newer
                </Link>
                <span class="text-stone-400">{{ posts.current_page }} / {{ posts.last_page }}</span>
                <Link
                    v-if="posts.next_page_url"
                    :href="posts.next_page_url"
                    class="text-stone-700 underline-offset-2 hover:underline"
                    preserve-scroll
                >
                    Older
                </Link>
            </div>
        </div>
    </StoreLayout>
</template>
