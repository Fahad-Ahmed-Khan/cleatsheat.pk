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

            <h1 class="text-display-md text-stadium-ink">

                {{ title }}

            </h1>

            <div class="store-prose mt-8" v-html="body" />

        </article>

    </StoreLayout>

</template>



<style scoped>

.store-prose :deep(h2),

.store-prose :deep(h3),

.store-prose :deep(h4) {

    margin-top: 2rem;

    margin-bottom: 0.75rem;

    font-family: inherit;

    font-weight: 700;

    letter-spacing: -0.02em;

    color: rgb(var(--stadium-ink));

}



.store-prose :deep(h2) {

    font-size: 1.25rem;

}



.store-prose :deep(h3) {

    font-size: 1.0625rem;

}



.store-prose :deep(p),

.store-prose :deep(li) {

    font-size: 0.9375rem;

    line-height: 1.65;

    color: rgb(var(--stadium-secondary));

}



.store-prose :deep(strong) {

    color: rgb(var(--stadium-ink));

    font-weight: 600;

}



.store-prose :deep(a) {

    color: rgb(var(--store-secondary));

    font-weight: 600;

    text-decoration: underline;

    text-underline-offset: 2px;

}



.store-prose :deep(a:hover) {

    color: rgb(var(--stadium-ink));

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


