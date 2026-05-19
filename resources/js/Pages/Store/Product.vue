<script setup>
import StoreBargainPanel from '@/Components/Store/StoreBargainPanel.vue';
import StoreBottomSheet from '@/Components/Store/StoreBottomSheet.vue';
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreProductGallery from '@/Components/Store/StoreProductGallery.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreTrustStrip from '@/Components/Store/StoreTrustStrip.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { useStoreFormat } from '@/composables/useStoreFormat';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
    sizeChart: { type: Object, default: null },
    reviews: { type: Array, default: () => [] },
    relatedProducts: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});

const { formatPKR } = useStoreFormat();
const page = usePage();
const storefront = computed(() => page.props.storefront ?? {});
const storeBargainEnabled = computed(() => page.props.bargainEnabled !== false);
const analytics = useStoreAnalytics();

/** Phone to send with add-to-cart when a bargain lock exists for the selected variant */
const bargainPhoneForCart = ref('');
/** @type {import('vue').Ref<{ variantId: number } | null>} */
const bargainLock = ref(null);

onMounted(() => {
    const v = props.product.variants?.[0];
    analytics.trackViewItem({
        productId: props.product.id,
        name: props.product.name,
        category: props.product.category?.name ?? '',
        price: Number(v?.price ?? 0),
    });
});

const selectedVariantId = ref(props.product.variants?.[0]?.id ?? null);

function defaultSizeForVariant(variant) {
    if (!variant?.sizes?.length) {
        return null;
    }
    const firstInStock = variant.sizes.find((s) => s.in_stock);
    if (firstInStock) {
        return firstInStock.size_label;
    }
    if (variant.sizes.length === 1) {
        return variant.sizes[0].size_label;
    }
    return null;
}

const selectedSize = ref(defaultSizeForVariant(props.product.variants?.[0]));
const chartOpen = ref(false);
const sizeSheetOpen = ref(false);
/** @type {import('vue').Ref<'uk'|'eu'|'pk'>} */
const sizeSystem = ref('uk');
/** @type {import('vue').Ref<InstanceType<typeof StoreBargainPanel> | null>} */
const bargainPanelRef = ref(null);

const selectedVariant = computed(() =>
    props.product.variants?.find((v) => v.id === selectedVariantId.value),
);

const selectedSizeRow = computed(() => {
    if (!selectedVariant.value || !selectedSize.value) return null;
    return selectedVariant.value.sizes?.find((s) => s.size_label === selectedSize.value) ?? null;
});

watch(
    () => props.product.variants,
    (variants) => {
        if (!variants?.length) return;
        const exists = variants.some((v) => v.id === selectedVariantId.value);
        if (!exists) {
            selectedVariantId.value = variants[0].id;
            selectedSize.value = null;
        }
    },
    { immediate: true },
);

watch(
    selectedVariant,
    (v) => {
        selectedSize.value = defaultSizeForVariant(v);
    },
    { immediate: true },
);

watch(selectedVariantId, (id) => {
    if (bargainLock.value && bargainLock.value.variantId !== id) {
        bargainPhoneForCart.value = '';
        bargainLock.value = null;
    }
});

function onBargainLocked({ phone }) {
    bargainPhoneForCart.value = phone;
    bargainLock.value = { variantId: selectedVariantId.value };
}

function onBargainCleared() {
    bargainPhoneForCart.value = '';
    bargainLock.value = null;
}

const bargainAppliesToSelection = computed(
    () =>
        !!bargainLock.value &&
        !!selectedVariant.value &&
        bargainLock.value.variantId === selectedVariant.value.id &&
        !!bargainPhoneForCart.value,
);

const fitLabels = {
    true_to_size: 'True to size',
    runs_small: 'Runs small',
    runs_large: 'Runs large',
};

const fitReviewLabels = {
    runs_small: 'Runs small',
    true_to_size: 'True to size',
    runs_large: 'Runs large',
};

function formatPrice(n) {
    return formatPKR(n);
}

function sizeStock(sizeLabel) {
    const s = selectedVariant.value?.sizes?.find((x) => x.size_label === sizeLabel);
    return s?.stock_qty ?? 0;
}

function isSizeAvailable(sizeLabel) {
    return sizeStock(sizeLabel) > 0;
}

const canAddToCart = computed(
    () => selectedVariant.value && selectedSize.value && isSizeAvailable(selectedSize.value),
);

const saleBadgeVisible = computed(() => {
    const v = selectedVariant.value;
    if (!v?.compare_at_price) {
        return false;
    }
    return Number(v.compare_at_price) > Number(v.price ?? 0);
});

const bargainAvailableOnVariant = computed(
    () => storeBargainEnabled.value && !!selectedVariant.value?.bargain_enabled,
);

function openBargainChat() {
    bargainPanelRef.value?.openPanel();
}

const hasMultipleVariants = computed(() => (props.product.variants?.length ?? 0) > 1);
const hasMultipleSizes = computed(() => (selectedVariant.value?.sizes?.length ?? 0) > 1);

const gallery = computed(() => props.product.images ?? []);

function rowCm(row) {
    if (row.foot_cm != null && row.foot_cm !== '') {
        return row.foot_cm;
    }
    return row.measurements?.cm ?? null;
}

function shortUkLabel(sizeLabel) {
    return String(sizeLabel).replace(/^UK\s*/i, '');
}

function displaySizeLabel(s) {
    if (sizeSystem.value === 'uk') {
        return s.uk_size || shortUkLabel(s.size_label);
    }
    if (sizeSystem.value === 'eu') {
        return s.eu_size || '—';
    }
    return s.pk_size || '—';
}

function subSizeCaption(s) {
    if (sizeSystem.value === 'uk') {
        return s.eu_size ? `EU ${s.eu_size}` : '';
    }
    if (sizeSystem.value === 'eu') {
        return s.uk_size ? `UK ${s.uk_size}` : '';
    }
    return s.uk_size ? `UK ${s.uk_size}` : '';
}

function selectSize(label) {
    selectedSize.value = label;
    sizeSheetOpen.value = false;
}

function addToBag() {
    if (!canAddToCart.value || !selectedVariant.value || !selectedSize.value) {
        return;
    }
    const payload = {
        product_variant_id: selectedVariant.value.id,
        size_label: selectedSize.value,
        quantity: 1,
    };
    if (bargainAppliesToSelection.value) {
        payload.bargain_phone = bargainPhoneForCart.value;
    }
    router.post(
        route('store.cart.add'),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                analytics.trackAddToCart({
                    productId: props.product.id,
                    name: props.product.name,
                    price: Number(selectedVariant.value?.price ?? 0),
                    quantity: 1,
                });
            },
        },
    );
}

function primaryCta() {
    if (canAddToCart.value) {
        addToBag();
        return;
    }
    if (hasMultipleSizes.value) {
        sizeSheetOpen.value = true;
    }
}

const primaryCtaLabel = computed(() => {
    if (canAddToCart.value) {
        return 'Add to bag';
    }
    if (selectedSize.value && !isSizeAvailable(selectedSize.value)) {
        return 'Out of stock';
    }
    return hasMultipleSizes.value ? 'Select size' : 'Select a size';
});

function stars(n) {
    const v = Math.min(5, Math.max(0, Math.round(Number(n) || 0)));
    return `${'★'.repeat(v)}${'☆'.repeat(5 - v)}`;
}
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-content pb-44 sm:pb-14 lg:pb-12">
            <div class="sm:grid sm:grid-cols-[1.1fr_1fr] sm:gap-10 sm:px-6 sm:pt-10 lg:gap-14">
                <div class="px-4 pt-4 sm:px-0 sm:pt-0">
                    <StoreProductGallery
                        :images="gallery"
                        :product-name="product.name"
                        :video-url="product.video_url || ''"
                        :video-poster="product.video_poster || ''"
                    />
                </div>

                <div class="px-4 pt-6 sm:px-0 sm:pt-0">
                    <p class="font-display text-[11px] font-bold uppercase tracking-[0.15em] text-stadium-secondary">
                        {{ product.brand?.name }}
                    </p>
                    <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tighter text-stadium-ink sm:text-4xl">
                        {{ product.name }}
                    </h1>
                    <p v-if="product.category?.name" class="mt-2 text-lg font-medium text-stadium-secondary">
                        {{ product.category.name }}
                    </p>

                    <div v-if="saleBadgeVisible || product.fit_guidance" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-if="saleBadgeVisible"
                            class="rounded-full bg-stadium-ink px-3 py-1.5 font-display text-xs font-bold uppercase tracking-wide text-stadium-lime"
                        >
                            Sale
                        </span>
                        <span
                            v-if="product.fit_guidance"
                            class="rounded-full bg-stadium-lime/25 px-3 py-1.5 font-display text-xs font-bold uppercase tracking-wide text-stadium-lime-muted ring-1 ring-stadium-outline-soft/60"
                        >
                            Fit: {{ fitLabels[product.fit_guidance] ?? product.fit_guidance }}
                        </span>
                    </div>

                    <template v-if="selectedVariant">
                        <p class="mt-5 flex flex-wrap items-baseline gap-2">
                            <span class="font-display text-3xl font-bold tabular-nums text-stadium-ink">{{
                                formatPrice(selectedVariant.price)
                            }}</span>
                            <span
                                v-if="selectedVariant.compare_at_price"
                                class="text-base tabular-nums text-stadium-outline line-through"
                            >
                                {{ formatPrice(selectedVariant.compare_at_price) }}
                            </span>
                        </p>

                        <div
                            class="mt-6 flex items-center justify-between rounded-xl border border-stadium-outline-soft/40 bg-stadium-muted px-4 py-3"
                            aria-hidden="true"
                        >
                            <span class="flex items-center gap-2 font-display text-xs font-bold uppercase tracking-wide text-stadium-ink">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-stadium-ink text-sm text-stadium-lime">⚡</span>
                                Stud grip
                            </span>
                            <div class="h-2 w-28 overflow-hidden rounded-full bg-stadium-container">
                                <div class="h-full w-[82%] rounded-full bg-stadium-lime" />
                            </div>
                        </div>
                    </template>
                    <div
                        v-if="product.description"
                        class="prose prose-sm prose-stone mt-8 max-w-none leading-relaxed"
                        v-html="product.description"
                    />

                    <ul
                        v-if="product.features?.length"
                        class="mt-8 space-y-3 border-t border-stadium-outline-soft pt-8"
                    >
                        <li
                            v-for="(f, i) in product.features"
                            :key="i"
                            class="flex gap-3 text-sm text-stadium-ink"
                        >
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-stadium-lime/35 text-xs font-bold text-stadium-ink">✓</span>
                            <span>{{ f }}</span>
                        </li>
                    </ul>

                    <p
                        v-if="product.fit_notes"
                        class="mt-8 rounded-2xl bg-amber-50 p-4 text-sm leading-relaxed text-amber-950 ring-1 ring-amber-200/90"
                    >
                        <span class="font-semibold">Fit tip:</span>
                        {{ product.fit_notes }}
                    </p>

                    <div
                        v-if="product.size_info"
                        class="prose prose-sm prose-stone mt-6 max-w-none rounded-2xl bg-stadium-muted/80 p-4 text-stadium-secondary ring-1 ring-stadium-outline-soft/80"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-stadium-secondary">
                            Sizing notes
                        </p>
                        <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed" v-text="product.size_info" />
                    </div>

                    <!-- Colour (only shown when there are multiple variants) -->
                    <div v-if="hasMultipleVariants" class="mt-10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stadium-secondary">
                            Colour
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="v in product.variants"
                                :key="v.id"
                                type="button"
                                class="min-h-12 rounded-full px-5 py-2.5 text-sm font-semibold transition active:scale-[0.98]"
                                :class="
                                    v.id === selectedVariantId
                                        ? 'bg-stadium-ink text-white shadow-md'
                                        : 'bg-white text-stadium-secondary ring-1 ring-stadium-outline-soft hover:ring-stadium-outline'
                                "
                                @click="selectedVariantId = v.id"
                            >
                                {{ v.color?.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Size -->
                    <div class="mt-10">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stadium-secondary">
                                Size
                            </p>
                            <div class="flex items-center gap-2">
                                <div
                                    v-if="hasMultipleSizes"
                                    class="inline-flex rounded-full bg-stadium-muted p-1 ring-1 ring-stadium-outline-soft/90"
                                >
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'uk' ? 'bg-white text-stadium-ink shadow-sm' : 'text-stadium-secondary'"
                                        @click="sizeSystem = 'uk'"
                                    >
                                        UK
                                    </button>
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'eu' ? 'bg-white text-stadium-ink shadow-sm' : 'text-stadium-secondary'"
                                        @click="sizeSystem = 'eu'"
                                    >
                                        EU
                                    </button>
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'pk' ? 'bg-white text-stadium-ink shadow-sm' : 'text-stadium-secondary'"
                                        @click="sizeSystem = 'pk'"
                                    >
                                        PK
                                    </button>
                                </div>
                                <button
                                    v-if="sizeChart"
                                    type="button"
                                    class="text-xs font-semibold text-stadium-ink underline decoration-stadium-outline-soft underline-offset-4"
                                    @click="chartOpen = true"
                                >
                                    Chart
                                </button>
                            </div>
                        </div>

                        <!-- Single available size: show as a read-only chip -->
                        <div
                            v-if="!hasMultipleSizes && selectedSizeRow"
                            class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-stadium-muted px-4 py-3 text-sm font-semibold text-stadium-ink ring-1 ring-stadium-outline-soft"
                        >
                            <span class="tabular-nums">UK {{ selectedSizeRow.uk_size || shortUkLabel(selectedSize) }}</span>
                            <span v-if="selectedSizeRow.eu_size" class="text-stadium-secondary font-medium tabular-nums">· EU {{ selectedSizeRow.eu_size }}</span>
                            <span v-if="selectedSizeRow.pk_size" class="text-stadium-secondary font-medium tabular-nums">· PK {{ selectedSizeRow.pk_size }}</span>
                        </div>

                        <!-- Multi-size: desktop grid -->
                        <div
                            v-if="hasMultipleSizes"
                            class="mt-4 hidden grid-cols-4 gap-2 sm:grid sm:grid-cols-5"
                        >
                            <button
                                v-for="s in selectedVariant?.sizes ?? []"
                                :key="s.size_label"
                                type="button"
                                :disabled="!s.in_stock"
                                class="flex min-h-[3.25rem] flex-col items-center justify-center rounded-2xl px-1 py-2 text-center transition active:scale-[0.98]"
                                :class="[
                                    selectedSize === s.size_label
                                        ? 'bg-stadium-ink text-white shadow-md'
                                        : s.in_stock
                                          ? 'bg-white text-stadium-ink ring-1 ring-stadium-outline-soft hover:ring-stadium-outline'
                                          : 'cursor-not-allowed bg-stadium-muted text-stadium-outline line-through ring-1 ring-stadium-muted',
                                ]"
                                @click="s.in_stock && selectSize(s.size_label)"
                            >
                                <span class="text-sm font-bold tabular-nums">{{ displaySizeLabel(s) }}</span>
                                <span
                                    v-if="subSizeCaption(s)"
                                    class="mt-0.5 text-[10px] font-normal opacity-80"
                                    :class="selectedSize === s.size_label ? 'text-stadium-outline-soft' : 'text-stadium-secondary'"
                                >
                                    {{ subSizeCaption(s) }}
                                </span>
                            </button>
                        </div>

                        <!-- Multi-size: mobile chooser -->
                        <button
                            v-if="hasMultipleSizes"
                            type="button"
                            class="mt-4 flex w-full min-h-14 items-center justify-between rounded-2xl bg-white px-4 py-3 text-left shadow-sm ring-1 ring-stadium-outline-soft transition hover:ring-stadium-outline-soft sm:hidden active:scale-[0.99]"
                            @click="sizeSheetOpen = true"
                        >
                            <span class="text-sm font-semibold text-stadium-ink">
                                {{ selectedSize ? displaySizeLabel(selectedSizeRow) + ' · ' + selectedSize : 'Choose your size' }}
                            </span>
                            <span class="text-xs font-medium text-stadium-secondary">{{ sizeSystem.toUpperCase() }}</span>
                        </button>

                        <p v-if="selectedSize && !isSizeAvailable(selectedSize)" class="mt-3 text-sm text-red-600">
                            Out of stock for this size.
                        </p>
                        <p v-else-if="selectedSizeRow && selectedSize" class="mt-3 text-sm text-stadium-secondary">
                            <span class="font-semibold text-stadium-ink">{{ sizeStock(selectedSize) }}</span> in stock
                            <span v-if="selectedSizeRow.pk_size && sizeSystem !== 'pk'" class="text-stadium-secondary">
                                · PK {{ selectedSizeRow.pk_size }}
                            </span>
                        </p>
                    </div>

                    <p
                        v-if="bargainAppliesToSelection"
                        class="mt-6 rounded-xl bg-stadium-lime/20 px-3 py-2 text-xs font-semibold text-stadium-ink ring-1 ring-stadium-lime/40"
                    >
                        Bargain price will apply — same phone as in the bargain chat is sent with add to bag.
                    </p>

                    <button
                        type="button"
                        class="mt-6 hidden w-full min-h-14 rounded-2xl bg-stadium-lime px-6 text-base font-bold text-stadium-ink shadow-lg transition hover:-translate-y-px hover:bg-stadium-lime/90 active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-stadium-outline-soft disabled:text-stadium-secondary sm:block"
                        :disabled="!canAddToCart"
                        @click="primaryCta"
                    >
                        {{ primaryCtaLabel }}
                    </button>

                    <StoreBargainPanel
                        v-if="selectedVariant"
                        ref="bargainPanelRef"
                        hide-mobile-fab
                        :store-bargain-enabled="storeBargainEnabled"
                        :product-variant-id="selectedVariant.id"
                        :list-price="Number(selectedVariant.price ?? 0)"
                        :variant-bargain-enabled="!!selectedVariant.bargain_enabled"
                        :color-name="selectedVariant.color?.name ?? ''"
                        @locked="onBargainLocked"
                        @cleared="onBargainCleared"
                    />

                    <!-- Find my size placeholder -->
                    <div class="mt-8 rounded-2xl border border-dashed border-stadium-outline-soft bg-stadium-muted/80 px-4 py-5 sm:px-5">
                        <p class="text-sm font-semibold text-stadium-ink">
                            Find my size
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-stadium-secondary">
                            Foot scan &amp; personalised fit recommendations are on the roadmap. For now, use the size chart or WhatsApp us a photo of your current sole length.
                        </p>
                    </div>

                    <StoreTrustStrip class="mt-10" />

                    <!-- Reviews -->
                    <section v-if="reviews.length" class="mt-12 border-t border-stadium-outline-soft pt-10">
                        <h2 class="text-lg font-semibold text-stadium-ink">
                            Reviews &amp; fit
                        </h2>
                        <p class="mt-1 text-sm text-stadium-secondary">
                            How shoppers felt about sizing — not medical advice.
                        </p>
                        <ul class="mt-6 space-y-5">
                            <li
                                v-for="r in reviews"
                                :key="r.id"
                                class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stadium-outline-soft/90"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-amber-500" aria-hidden="true">{{ stars(r.rating) }}</span>
                                    <span v-if="r.fit_feedback" class="rounded-full bg-stadium-muted px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-stadium-ink">
                                        Fit: {{ fitReviewLabels[r.fit_feedback] ?? r.fit_feedback }}
                                    </span>
                                </div>
                                <p class="mt-2 font-semibold text-stadium-ink">
                                    {{ r.title }}
                                </p>
                                <p v-if="r.body" class="mt-1 text-sm leading-relaxed text-stadium-secondary">
                                    {{ r.body }}
                                </p>
                                <p class="mt-3 text-xs text-stadium-outline">
                                    {{ r.author_display }}
                                </p>
                            </li>
                        </ul>
                    </section>

                </div>
            </div>

            <!-- Related products -->
            <section
                v-if="relatedProducts.length"
                class="mt-16 border-t border-stadium-outline-soft px-4 pt-12 sm:px-6"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-stadium-ink sm:text-xl">
                            You may also like
                        </h2>
                        <p class="mt-1 text-sm text-stadium-secondary">
                            More from
                            <span v-if="product.category?.name">{{ product.category.name }}</span>
                            <span v-else-if="product.brand?.name">{{ product.brand.name }}</span>
                            <span v-else>our catalogue</span>.
                        </p>
                    </div>
                    <a
                        v-if="product.category?.slug"
                        :href="route('store.category', product.category.slug)"
                        class="text-xs font-semibold text-stadium-ink underline decoration-stadium-outline-soft underline-offset-4 hover:text-stadium-ink"
                    >
                        Shop all
                    </a>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 lg:gap-5">
                    <StoreProductCard
                        v-for="(rp, i) in relatedProducts"
                        :key="rp.id"
                        :product="rp"
                        :index="i"
                    />
                </div>
            </section>
        </div>

        <!-- Mobile sticky purchase bar (sits above the bottom navigation) -->
        <div
            class="store-sticky-above-nav fixed inset-x-0 z-[45] border-t border-stadium-outline-soft bg-stadium-white px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] sm:hidden"
        >
            <div class="mx-auto flex max-w-lg items-center gap-2">
                <button
                    v-if="bargainAvailableOnVariant"
                    type="button"
                    class="flex min-h-12 shrink-0 items-center justify-center gap-1 rounded-2xl bg-emerald-700 px-3 text-xs font-bold text-white shadow-sm transition active:scale-[0.98]"
                    aria-label="Open bargain chat"
                    @click="openBargainChat"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                    </svg>
                    <span class="sr-only min-[400px]:not-sr-only min-[400px]:inline">Bargain</span>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-stadium-secondary">
                        {{ selectedVariant?.color?.name }} · {{ selectedSize ? selectedSize : 'Pick size' }}
                    </p>
                    <p v-if="selectedVariant" class="text-base font-bold tabular-nums text-stadium-ink">
                        {{ formatPrice(selectedVariant.price) }}
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-12 shrink-0 rounded-2xl bg-stadium-lime px-4 text-sm font-bold text-stadium-ink shadow-md transition hover:bg-stadium-lime/90 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-stadium-outline-soft disabled:text-stadium-secondary min-[400px]:min-h-14 min-[400px]:px-6"
                    :disabled="!canAddToCart && !hasMultipleSizes"
                    @click="primaryCta"
                >
                    {{ primaryCtaLabel }}
                </button>
            </div>
        </div>

        <!-- Size chart -->
        <StoreBottomSheet :open="chartOpen" title="Size guide" z-class="z-[70]" @close="chartOpen = false">
            <div v-if="sizeChart" class="mt-3">
                <p class="text-sm text-stadium-secondary">
                    {{ sizeChart.name }} — UK, EU, PK and cm are approximate.
                </p>
                <div class="mt-4 overflow-x-auto rounded-xl ring-1 ring-stadium-outline-soft">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-stadium-outline-soft bg-stadium-muted text-xs uppercase text-stadium-secondary">
                                <th class="px-3 py-2">Label</th>
                                <th class="px-3 py-2">UK</th>
                                <th class="px-3 py-2">EU</th>
                                <th class="px-3 py-2">PK</th>
                                <th class="px-3 py-2">CM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in sizeChart.rows"
                                :key="idx"
                                class="border-b border-stadium-muted"
                            >
                                <td class="px-3 py-2.5 font-medium text-stadium-ink">{{ row.label }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stadium-ink">{{ row.uk_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stadium-ink">{{ row.eu_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stadium-ink">{{ row.pk_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stadium-secondary">
                                    {{ rowCm(row) != null ? rowCm(row) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button
                    type="button"
                    class="mt-6 w-full min-h-12 rounded-2xl bg-stadium-lime text-sm font-bold text-stadium-ink"
                    @click="chartOpen = false"
                >
                    Done
                </button>
            </div>
        </StoreBottomSheet>

        <!-- Mobile size sheet -->
        <StoreBottomSheet :open="sizeSheetOpen" title="Select size" z-class="z-[70]" @close="sizeSheetOpen = false">
            <div class="mt-4 space-y-4">
                <div class="inline-flex rounded-full bg-stadium-muted p-1 ring-1 ring-stadium-outline-soft">
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'uk' ? 'bg-white shadow-sm' : 'text-stadium-secondary'"
                        @click="sizeSystem = 'uk'"
                    >
                        UK
                    </button>
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'eu' ? 'bg-white shadow-sm' : 'text-stadium-secondary'"
                        @click="sizeSystem = 'eu'"
                    >
                        EU
                    </button>
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'pk' ? 'bg-white shadow-sm' : 'text-stadium-secondary'"
                        @click="sizeSystem = 'pk'"
                    >
                        PK
                    </button>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="s in selectedVariant?.sizes ?? []"
                        :key="'ms-' + s.size_label"
                        type="button"
                        :disabled="!s.in_stock"
                        class="flex min-h-[3.25rem] flex-col items-center justify-center rounded-2xl py-2 transition active:scale-[0.97]"
                        :class="[
                            selectedSize === s.size_label
                                ? 'bg-stadium-ink text-white'
                                : s.in_stock
                                  ? 'bg-stadium-muted ring-1 ring-stadium-outline-soft'
                                  : 'cursor-not-allowed opacity-40 line-through',
                        ]"
                        @click="s.in_stock && selectSize(s.size_label)"
                    >
                        <span class="text-sm font-bold tabular-nums">{{ displaySizeLabel(s) }}</span>
                        <span v-if="subSizeCaption(s)" class="mt-0.5 text-[10px] opacity-80">{{ subSizeCaption(s) }}</span>
                    </button>
                </div>
                <button
                    type="button"
                    class="w-full min-h-12 rounded-2xl bg-stadium-lime text-sm font-bold text-stadium-ink disabled:bg-stadium-outline-soft disabled:text-stadium-secondary"
                    :disabled="!canAddToCart"
                    @click="addToBag"
                >
                    Add to bag
                </button>
            </div>
        </StoreBottomSheet>
    </StoreLayout>
</template>
