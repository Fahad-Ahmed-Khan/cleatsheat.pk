<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    post: { type: Object, required: true },
    seo: { type: Object, required: true },
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <Link :href="route('store.journal.index')" class="text-xs font-medium text-stone-500 hover:text-stone-800">
                ← Journal
            </Link>
            <p v-if="post.pillar_keyword" class="mt-4 text-[11px] font-semibold uppercase tracking-widest text-emerald-700">
                {{ post.pillar_keyword }}
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">
                {{ post.title }}
            </h1>
            <p v-if="post.published_at" class="mt-2 text-xs text-stone-500">
                {{ new Date(post.published_at).toLocaleDateString('en-PK', { dateStyle: 'medium' }) }}
            </p>
            <div
                v-if="post.body"
                class="prose prose-stone prose-lg mt-10 max-w-none"
                v-html="post.body"
            />
        </article>
    </StoreLayout>
</template>
