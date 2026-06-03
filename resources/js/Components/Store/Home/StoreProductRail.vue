<script setup>
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreProductCardSkeleton from '@/Components/Store/StoreProductCardSkeleton.vue';
import StoreProductRailCta from '@/Components/Store/Home/StoreProductRailCta.vue';
import StoreSectionHeader from '@/Components/Store/StoreSectionHeader.vue';
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    products: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    bgClass: { type: String, default: 'bg-stadium' },
    scrollMobile: { type: Boolean, default: false },
    viewAllHref: { type: String, default: '' },
    viewAllLabel: { type: String, default: '' },
    showViewAllCta: { type: Boolean, default: true },
});

const shopHref = computed(() => props.viewAllHref || route('store.shop'));
const ctaLabel = computed(() => props.viewAllLabel || 'View all cleats');
const showCta = computed(() => props.showViewAllCta && props.products.length > 0 && !props.loading);
</script>

<template>
    <section
        :id="id || undefined"
        class="store-section border-t border-stadium-outline-soft/20 scroll-mt-24"
        :class="bgClass"
    >
        <div class="store-container">
            <StoreSectionHeader :title="title" :subtitle="subtitle">
                <template v-if="$slots.action" #action>
                    <slot name="action" />
                </template>
            </StoreSectionHeader>

            <div class="mt-8">
                <div
                    v-if="loading"
                    class="store-product-grid"
                >
                    <StoreProductCardSkeleton v-for="n in 4" :key="n" />
                </div>
                <div
                    v-else-if="scrollMobile"
                    class="no-scrollbar -mx-5 flex gap-3 overflow-x-auto px-5 pb-2 snap-x sm:-mx-8 sm:px-8 md:mx-0 md:store-product-grid md:overflow-visible md:px-0 md:pb-0 md:snap-none"
                >
                    <div
                        v-for="(p, i) in products"
                        :key="p.id"
                        class="w-[min(72vw,280px)] shrink-0 snap-start md:w-auto"
                    >
                        <StoreProductCard :product="p" :index="i" :eager-image="scrollMobile && i < 2" />
                    </div>
                    <div
                        v-if="showCta"
                        class="order-last w-[min(72vw,280px)] shrink-0 snap-start md:order-last md:w-auto"
                    >
                        <StoreProductRailCta :href="shopHref" :label="ctaLabel" />
                    </div>
                </div>
                <div v-else class="store-product-grid">
                    <StoreProductCard
                        v-for="(p, i) in products"
                        :key="p.id"
                        :product="p"
                        :index="i"
                    />
                    <StoreProductRailCta
                        v-if="showCta"
                        class="order-last"
                        :href="shopHref"
                        :label="ctaLabel"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
