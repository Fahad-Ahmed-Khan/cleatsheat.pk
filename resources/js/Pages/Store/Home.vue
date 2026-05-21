<script setup>

import StoreHomeBuyingGuides from '@/Components/Store/Home/StoreHomeBuyingGuides.vue';

import StoreHomeBrandsCarousel from '@/Components/Store/Home/StoreHomeBrandsCarousel.vue';

import StoreHomeHero from '@/Components/Store/Home/StoreHomeHero.vue';

import StoreHomeNewsletter from '@/Components/Store/Home/StoreHomeNewsletter.vue';

import StoreHomeSeoContent from '@/Components/Store/Home/StoreHomeSeoContent.vue';

import StoreHomeSocialProof from '@/Components/Store/Home/StoreHomeSocialProof.vue';

import StoreHomeCategoryBrowser from '@/Components/Store/Home/StoreHomeCategoryBrowser.vue';

import StoreHomeTestimonials from '@/Components/Store/Home/StoreHomeTestimonials.vue';

import StoreHomeTrust from '@/Components/Store/Home/StoreHomeTrust.vue';

import StoreProductRail from '@/Components/Store/Home/StoreProductRail.vue';

import StoreProductQuickAddSheet from '@/Components/Store/StoreProductQuickAddSheet.vue';

import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';

import StoreLayout from '@/Layouts/StoreLayout.vue';

import { useStoreQuickAdd } from '@/composables/useStoreQuickAdd';

import { Link } from '@inertiajs/vue3';

import { computed, onUnmounted, provide } from 'vue';

const props = defineProps({

    featured: { type: Array, default: () => [] },

    bestSellers: { type: Array, default: () => [] },

    newArrivals: { type: Array, default: () => [] },

    trending: { type: Array, default: () => [] },

    categories: { type: Array, default: () => [] },

    hero: { type: Object, required: true },

    promoBanner: { type: Object, default: () => ({}) },

    homeContent: { type: Object, default: () => ({}) },

    journalPosts: { type: Array, default: () => [] },

    seo: { type: Object, required: true },

    shopProductCount: { type: Number, default: 0 },

    brands: { type: Array, default: () => [] },

});

const viewAllCleatsLabel = computed(() => {
    const n = Number(props.shopProductCount ?? 0);
    if (n >= 50) return `View all ${n}+ cleats`;
    if (n > 0) return `View all ${n} cleats`;
    return 'View all cleats';
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
        />

        <StoreHomeCategoryBrowser :categories="categories" />



        <StoreProductRail

            v-if="featured.length"

            title="Staff picks"

            subtitle="Curated pairs our team would wear every week."

            :products="featured"

            bg-class="bg-stadium"

            scroll-mobile

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            id="best-sellers"

            title="Best sellers"

            subtitle="Most-loved silhouettes — yours might be one tap away."

            :products="bestSellers"

            bg-class="bg-stadium-white"

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            title="New arrivals"

            subtitle="Fresh drops with updated sizing charts."

            :products="newArrivals"

            bg-class="bg-stadium-container-low"

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            id="trending"

            title="Trending on pitch"

            subtitle="What shoppers are viewing right now."

            :products="trending"

            bg-class="bg-stadium-white"

            :view-all-label="viewAllCleatsLabel"

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



        <StoreHomeBrandsCarousel :brands="brands" />



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


