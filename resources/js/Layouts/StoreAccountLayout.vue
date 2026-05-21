<script setup>
import StoreAccountNav from '@/Components/Store/Account/StoreAccountNav.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    seo: { type: Object, required: true },
    title: { type: String, default: '' },
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="store-container pb-28 pt-8 md:pb-16 md:pt-12">
            <h1 v-if="title" class="text-display-md text-stadium-ink">
                {{ title }}
            </h1>
            <div class="mt-8 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
                <aside class="lg:sticky lg:top-28">
                    <StoreAccountNav />
                </aside>
                <div class="min-w-0">
                    <slot />
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
