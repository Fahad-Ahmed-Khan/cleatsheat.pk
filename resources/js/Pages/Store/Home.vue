<script setup>

import StoreHomeBuyingGuides from '@/Components/Store/Home/StoreHomeBuyingGuides.vue';

import StoreHomeCategoryChips from '@/Components/Store/Home/StoreHomeCategoryChips.vue';

import StoreHomeHero from '@/Components/Store/Home/StoreHomeHero.vue';

import StoreHomeNewsletter from '@/Components/Store/Home/StoreHomeNewsletter.vue';

import StoreHomeSeoContent from '@/Components/Store/Home/StoreHomeSeoContent.vue';

import StoreHomeSocialProof from '@/Components/Store/Home/StoreHomeSocialProof.vue';

import StoreHomeSurfaceSelector from '@/Components/Store/Home/StoreHomeSurfaceSelector.vue';

import StoreHomeTestimonials from '@/Components/Store/Home/StoreHomeTestimonials.vue';

import StoreHomeTrust from '@/Components/Store/Home/StoreHomeTrust.vue';

import StoreProductRail from '@/Components/Store/Home/StoreProductRail.vue';

import StoreProductQuickAddSheet from '@/Components/Store/StoreProductQuickAddSheet.vue';

import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';

import StoreLayout from '@/Layouts/StoreLayout.vue';

import { useStoreQuickAdd } from '@/composables/useStoreQuickAdd';

import { Link } from '@inertiajs/vue3';

import { onUnmounted, provide } from 'vue';



defineProps({

    featured: { type: Array, default: () => [] },

    bestSellers: { type: Array, default: () => [] },

    newArrivals: { type: Array, default: () => [] },

    trending: { type: Array, default: () => [] },

    categories: { type: Array, default: () => [] },

    surfaceCategories: { type: Array, default: () => [] },

    hero: { type: Object, required: true },

    promoBanner: { type: Object, default: () => ({}) },

    homeContent: { type: Object, default: () => ({}) },

    journalPosts: { type: Array, default: () => [] },

    seo: { type: Object, required: true },

});



const {
    sheetOpen,
    sheetProduct,
    adding,
    quickAdd,
    addWithSize,
    closeSheet,
} = useStoreQuickAdd();

provide('storeQuickAdd', { quickAdd });

onUnmounted(closeSheet);

function onSheetSelect({ variantId, sizeLabel }) {
    if (!sheetProduct.value) return;
    addWithSize(sheetProduct.value, variantId, sizeLabel);
}

</script>



<template>

    <StoreSeoHead :seo="seo" />

    <StoreLayout>

        <StoreHomeHero
            :hero="hero"
            :promo-banner="promoBanner"
            :categories="categories"
            :surface-categories="surfaceCategories"
        />



        <StoreHomeSurfaceSelector :surface-categories="surfaceCategories" />



        <StoreHomeCategoryChips :categories="categories" />



        <StoreProductRail

            v-if="featured.length"

            title="Staff picks"

            subtitle="Curated pairs our team would wear every week."

            :products="featured"

            bg-class="bg-stadium"

            scroll-mobile

        />



        <StoreProductRail

            id="best-sellers"

            title="Best sellers"

            subtitle="Most-loved silhouettes — yours might be one tap away."

            :products="bestSellers"

            bg-class="bg-stadium-white"

        />



        <StoreProductRail

            title="New arrivals"

            subtitle="Fresh drops with updated sizing charts."

            :products="newArrivals"

            bg-class="bg-stadium-container-low"

        />



        <StoreProductRail

            id="trending"

            title="Trending on pitch"

            subtitle="What shoppers are viewing right now."

            :products="trending"

            bg-class="bg-stadium-white"

        >

            <template #action>

                <Link

                    :href="route('store.shop')"

                    class="inline-flex items-center gap-1 font-display text-sm font-bold uppercase tracking-wide text-stadium-olive hover:underline"

                >

                    View all

                    <span aria-hidden="true">→</span>

                </Link>

            </template>

        </StoreProductRail>



        <StoreHomeTrust />



        <StoreHomeTestimonials :testimonials="homeContent.testimonials ?? []" />



        <StoreHomeBuyingGuides :journal-posts="journalPosts" />



        <StoreHomeSocialProof :social="homeContent.social ?? {}" />



        <StoreHomeNewsletter :enabled="homeContent.newsletter_enabled !== false" />



        <StoreHomeSeoContent

            :categories="categories"

            :seo-html="homeContent.seo_html"

        />



        <StoreProductQuickAddSheet
            :open="sheetOpen"
            :product="sheetProduct"
            :adding="adding"
            @close="closeSheet"
            @select="onSheetSelect"
        />

    </StoreLayout>

</template>


