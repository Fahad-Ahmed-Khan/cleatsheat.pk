<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';

defineProps({
    title: { type: String, required: true },
    body: { type: String, required: true },
    seo: { type: Object, required: true },
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <h1 class="font-display text-3xl font-extrabold tracking-tight text-stadium-ink">
                {{ title }}
            </h1>
            <div class="store-prose mt-8 text-stadium-secondary" v-html="body" />
        </article>
    </StoreLayout>
</template>

<style scoped>
.store-prose :deep(h2) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-family: inherit;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-stadium-ink, #0f172a);
}
.store-prose :deep(p),
.store-prose :deep(li) {
    font-size: 0.9375rem;
    line-height: 1.65;
}
.store-prose :deep(ul) {
    margin-top: 0.5rem;
    list-style: disc;
    padding-left: 1.25rem;
}
.store-prose :deep(li + li) {
    margin-top: 0.35rem;
}
.store-prose :deep(p + p) {
    margin-top: 0.75rem;
}
</style>
