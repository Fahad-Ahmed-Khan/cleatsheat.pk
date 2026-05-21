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
        <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-14">
            <Link
                :href="route('store.journal.index')"
                class="inline-flex items-center gap-1 text-sm font-semibold text-stadium-olive hover:underline"
            >
                ← Back to journal
            </Link>

            <header class="mt-6 border-b border-stadium-outline-soft/40 pb-8">
                <p
                    v-if="post.pillar_keyword"
                    class="text-label text-stadium-olive"
                >
                    {{ post.pillar_keyword }}
                </p>
                <h1 class="mt-2 text-display-md text-stadium-ink">
                    {{ post.title }}
                </h1>
                <p v-if="post.published_at" class="mt-3 text-sm text-stadium-secondary">
                    {{ new Date(post.published_at).toLocaleDateString('en-PK', { dateStyle: 'medium' }) }}
                </p>
            </header>

            <div
                v-if="post.body"
                class="journal-prose mt-10"
                v-html="post.body"
            />
        </article>
    </StoreLayout>
</template>

<style scoped>
.journal-prose :deep(h2),
.journal-prose :deep(h3),
.journal-prose :deep(h4) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-family: inherit;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: rgb(var(--stadium-ink));
}

.journal-prose :deep(h2) {
    font-size: 1.375rem;
}

.journal-prose :deep(h3) {
    font-size: 1.125rem;
}

.journal-prose :deep(p),
.journal-prose :deep(li) {
    font-size: 1rem;
    line-height: 1.7;
    color: rgb(var(--stadium-secondary));
}

.journal-prose :deep(strong) {
    color: rgb(var(--stadium-ink));
    font-weight: 600;
}

.journal-prose :deep(a) {
    color: rgb(var(--store-secondary));
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.journal-prose :deep(a:hover) {
    color: rgb(var(--stadium-ink));
}

.journal-prose :deep(ul) {
    margin-top: 0.75rem;
    list-style: disc;
    padding-left: 1.25rem;
}

.journal-prose :deep(ol) {
    margin-top: 0.75rem;
    list-style: decimal;
    padding-left: 1.25rem;
}

.journal-prose :deep(li + li) {
    margin-top: 0.35rem;
}

.journal-prose :deep(p + p) {
    margin-top: 1rem;
}

.journal-prose :deep(blockquote) {
    margin-top: 1.5rem;
    border-left: 3px solid rgb(var(--store-primary));
    padding-left: 1rem;
    font-style: italic;
    color: rgb(var(--stadium-ink-variant));
}

.journal-prose :deep(figure),
.journal-prose :deep(.journal-figure) {
    margin-top: 1.5rem;
}

.journal-prose :deep(img) {
    border-radius: 1rem;
}

.journal-prose :deep(figcaption) {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: rgb(var(--stadium-secondary));
}

.journal-prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9375rem;
}

.journal-prose :deep(th),
.journal-prose :deep(td) {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid rgb(var(--stadium-outline-soft) / 0.5);
    text-align: left;
}

.journal-prose :deep(thead th) {
    border-bottom-width: 2px;
    color: rgb(var(--stadium-ink));
    font-weight: 600;
}

.journal-prose :deep(code) {
    border-radius: 0.25rem;
    background: rgb(var(--stadium-muted));
    padding: 0.125rem 0.375rem;
    font-size: 0.875em;
    color: rgb(var(--stadium-ink));
}
</style>
