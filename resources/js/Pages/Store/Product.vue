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
const selectedSize = ref(null);
const chartOpen = ref(false);
const sizeSheetOpen = ref(false);
/** @type {import('vue').Ref<'uk'|'eu'|'pk'>} */
const sizeSystem = ref('uk');

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

watch(selectedVariant, (v) => {
    selectedSize.value = null;
    if (!v?.sizes?.length) return;
    const firstInStock = v.sizes.find((s) => s.in_stock);
    if (firstInStock) {
        selectedSize.value = firstInStock.size_label;
    } else if (v.sizes.length === 1) {
        // Only one size, even if out of stock — auto-select it so the OOS state is shown clearly.
        selectedSize.value = v.sizes[0].size_label;
    }
});

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

function primaryCtaMobile() {
    if (canAddToCart.value) {
        addToBag();
    } else {
        sizeSheetOpen.value = true;
    }
}

function stars(n) {
    const v = Math.min(5, Math.max(0, Math.round(Number(n) || 0)));
    return `${'★'.repeat(v)}${'☆'.repeat(5 - v)}`;
}
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-6xl pb-36 sm:pb-14 lg:pb-12">
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">
                        {{ product.brand?.name }}
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">
                        {{ product.name }}
                    </h1>

                    <div
                        v-if="product.fit_guidance"
                        class="mt-4 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-900 ring-1 ring-emerald-200/90"
                    >
                        Fit: {{ fitLabels[product.fit_guidance] ?? product.fit_guidance }}
                    </div>

                    <p v-if="selectedVariant" class="mt-5 flex flex-wrap items-baseline gap-2">
                        <span class="text-2xl font-semibold tabular-nums text-stone-900">{{
                            formatPrice(selectedVariant.price)
                        }}</span>
                        <span
                            v-if="selectedVariant.compare_at_price"
                            class="text-base tabular-nums text-stone-400 line-through"
                        >
                            {{ formatPrice(selectedVariant.compare_at_price) }}
                        </span>
                    </p>

                    <div
                        v-if="product.description"
                        class="prose prose-sm prose-stone mt-8 max-w-none leading-relaxed"
                        v-html="product.description"
                    />

                    <ul
                        v-if="product.features?.length"
                        class="mt-8 space-y-3 border-t border-stone-200 pt-8"
                    >
                        <li
                            v-for="(f, i) in product.features"
                            :key="i"
                            class="flex gap-3 text-sm text-stone-700"
                        >
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs text-emerald-800">✓</span>
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
                        class="prose prose-sm prose-stone mt-6 max-w-none rounded-2xl bg-stone-100/80 p-4 text-stone-800 ring-1 ring-stone-200/80"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                            Sizing notes
                        </p>
                        <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed" v-text="product.size_info" />
                    </div>

                    <!-- Colour (only shown when there are multiple variants) -->
                    <div v-if="hasMultipleVariants" class="mt-10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
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
                                        ? 'bg-stone-900 text-white shadow-md'
                                        : 'bg-white text-stone-800 ring-1 ring-stone-200 hover:ring-stone-400'
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
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                                Size
                            </p>
                            <div class="flex items-center gap-2">
                                <div
                                    v-if="hasMultipleSizes"
                                    class="inline-flex rounded-full bg-stone-100 p-1 ring-1 ring-stone-200/90"
                                >
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'uk' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600'"
                                        @click="sizeSystem = 'uk'"
                                    >
                                        UK
                                    </button>
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'eu' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600'"
                                        @click="sizeSystem = 'eu'"
                                    >
                                        EU
                                    </button>
                                    <button
                                        type="button"
                                        class="min-h-9 min-w-[2.75rem] rounded-full px-3 text-xs font-semibold transition"
                                        :class="sizeSystem === 'pk' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600'"
                                        @click="sizeSystem = 'pk'"
                                    >
                                        PK
                                    </button>
                                </div>
                                <button
                                    v-if="sizeChart"
                                    type="button"
                                    class="text-xs font-semibold text-stone-900 underline decoration-stone-300 underline-offset-4"
                                    @click="chartOpen = true"
                                >
                                    Chart
                                </button>
                            </div>
                        </div>

                        <!-- Single available size: show as a read-only chip -->
                        <div
                            v-if="!hasMultipleSizes && selectedSizeRow"
                            class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-stone-100 px-4 py-3 text-sm font-semibold text-stone-900 ring-1 ring-stone-200"
                        >
                            <span class="tabular-nums">UK {{ selectedSizeRow.uk_size || shortUkLabel(selectedSize) }}</span>
                            <span v-if="selectedSizeRow.eu_size" class="text-stone-500 font-medium tabular-nums">· EU {{ selectedSizeRow.eu_size }}</span>
                            <span v-if="selectedSizeRow.pk_size" class="text-stone-500 font-medium tabular-nums">· PK {{ selectedSizeRow.pk_size }}</span>
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
                                        ? 'bg-stone-900 text-white shadow-md'
                                        : s.in_stock
                                          ? 'bg-white text-stone-900 ring-1 ring-stone-200 hover:ring-stone-400'
                                          : 'cursor-not-allowed bg-stone-100 text-stone-400 line-through ring-1 ring-stone-100',
                                ]"
                                @click="s.in_stock && selectSize(s.size_label)"
                            >
                                <span class="text-sm font-bold tabular-nums">{{ displaySizeLabel(s) }}</span>
                                <span
                                    v-if="subSizeCaption(s)"
                                    class="mt-0.5 text-[10px] font-normal opacity-80"
                                    :class="selectedSize === s.size_label ? 'text-stone-300' : 'text-stone-500'"
                                >
                                    {{ subSizeCaption(s) }}
                                </span>
                            </button>
                        </div>

                        <!-- Multi-size: mobile chooser -->
                        <button
                            v-if="hasMultipleSizes"
                            type="button"
                            class="mt-4 flex w-full min-h-14 items-center justify-between rounded-2xl bg-white px-4 py-3 text-left shadow-sm ring-1 ring-stone-200 transition hover:ring-stone-300 sm:hidden active:scale-[0.99]"
                            @click="sizeSheetOpen = true"
                        >
                            <span class="text-sm font-semibold text-stone-900">
                                {{ selectedSize ? displaySizeLabel(selectedSizeRow) + ' · ' + selectedSize : 'Choose your size' }}
                            </span>
                            <span class="text-xs font-medium text-stone-500">{{ sizeSystem.toUpperCase() }}</span>
                        </button>

                        <p v-if="selectedSize && !isSizeAvailable(selectedSize)" class="mt-3 text-sm text-red-600">
                            Out of stock for this size.
                        </p>
                        <p v-else-if="selectedSizeRow && selectedSize" class="mt-3 text-sm text-stone-600">
                            <span class="font-semibold text-stone-900">{{ sizeStock(selectedSize) }}</span> in stock
                            <span v-if="selectedSizeRow.pk_size && sizeSystem !== 'pk'" class="text-stone-500">
                                · PK {{ selectedSizeRow.pk_size }}
                            </span>
                        </p>
                    </div>

                    <p
                        v-if="bargainAppliesToSelection"
                        class="mt-6 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-950 ring-1 ring-emerald-200/80"
                    >
                        Bargain price will apply — same phone as in the bargain chat is sent with add to bag.
                    </p>

                    <button
                        type="button"
                        class="mt-6 hidden w-full min-h-14 rounded-2xl bg-stone-900 text-base font-semibold text-white shadow-lg transition hover:bg-stone-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-stone-300 sm:block"
                        :disabled="!canAddToCart"
                        @click="addToBag"
                    >
                        {{ canAddToCart ? 'Add to bag' : 'Select a size' }}
                    </button>

                    <StoreBargainPanel
                        v-if="selectedVariant"
                        :store-bargain-enabled="storeBargainEnabled"
                        :product-variant-id="selectedVariant.id"
                        :list-price="Number(selectedVariant.price ?? 0)"
                        :variant-bargain-enabled="!!selectedVariant.bargain_enabled"
                        :color-name="selectedVariant.color?.name ?? ''"
                        @locked="onBargainLocked"
                        @cleared="onBargainCleared"
                    />

                    <!-- Find my size placeholder -->
                    <div class="mt-8 rounded-2xl border border-dashed border-stone-300 bg-stone-50/80 px-4 py-5 sm:px-5">
                        <p class="text-sm font-semibold text-stone-900">
                            Find my size
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-stone-600">
                            Foot scan &amp; personalised fit recommendations are on the roadmap. For now, use the size chart or WhatsApp us a photo of your current sole length.
                        </p>
                    </div>

                    <StoreTrustStrip class="mt-10" />

                    <!-- Reviews -->
                    <section v-if="reviews.length" class="mt-12 border-t border-stone-200 pt-10">
                        <h2 class="text-lg font-semibold text-stone-900">
                            Reviews &amp; fit
                        </h2>
                        <p class="mt-1 text-sm text-stone-500">
                            How shoppers felt about sizing — not medical advice.
                        </p>
                        <ul class="mt-6 space-y-5">
                            <li
                                v-for="r in reviews"
                                :key="r.id"
                                class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200/90"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-amber-500" aria-hidden="true">{{ stars(r.rating) }}</span>
                                    <span v-if="r.fit_feedback" class="rounded-full bg-stone-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-stone-700">
                                        Fit: {{ fitReviewLabels[r.fit_feedback] ?? r.fit_feedback }}
                                    </span>
                                </div>
                                <p class="mt-2 font-semibold text-stone-900">
                                    {{ r.title }}
                                </p>
                                <p v-if="r.body" class="mt-1 text-sm leading-relaxed text-stone-600">
                                    {{ r.body }}
                                </p>
                                <p class="mt-3 text-xs text-stone-400">
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
                class="mt-16 border-t border-stone-200 px-4 pt-12 sm:px-6"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-stone-900 sm:text-xl">
                            You may also like
                        </h2>
                        <p class="mt-1 text-sm text-stone-500">
                            More from
                            <span v-if="product.category?.name">{{ product.category.name }}</span>
                            <span v-else-if="product.brand?.name">{{ product.brand.name }}</span>
                            <span v-else>our catalogue</span>.
                        </p>
                    </div>
                    <a
                        v-if="product.category?.slug"
                        :href="route('store.category', product.category.slug)"
                        class="text-xs font-semibold text-stone-900 underline decoration-stone-300 underline-offset-4 hover:text-stone-700"
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
            class="fixed inset-x-0 z-40 border-t border-stone-200/90 bg-white/95 px-4 pt-3 backdrop-blur-md sm:hidden"
            style="bottom: calc(4rem + env(safe-area-inset-bottom)); padding-bottom: 0.75rem"
        >
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-stone-500">
                        {{ selectedVariant?.color?.name }} · {{ selectedSize ? selectedSize : 'Pick size' }}
                    </p>
                    <p v-if="selectedVariant" class="text-lg font-semibold tabular-nums text-stone-900">
                        {{ formatPrice(selectedVariant.price) }}
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-14 shrink-0 rounded-2xl bg-stone-900 px-6 text-sm font-semibold text-white shadow-md transition active:scale-[0.98]"
                    @click="primaryCtaMobile"
                >
                    {{ canAddToCart ? 'Add to bag' : 'Choose size' }}
                </button>
            </div>
        </div>

        <!-- Size chart -->
        <StoreBottomSheet :open="chartOpen" title="Size guide" z-class="z-[70]" @close="chartOpen = false">
            <div v-if="sizeChart" class="mt-3">
                <p class="text-sm text-stone-600">
                    {{ sizeChart.name }} — UK, EU, PK and cm are approximate.
                </p>
                <div class="mt-4 overflow-x-auto rounded-xl ring-1 ring-stone-200">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
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
                                class="border-b border-stone-100"
                            >
                                <td class="px-3 py-2.5 font-medium text-stone-900">{{ row.label }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stone-700">{{ row.uk_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stone-700">{{ row.eu_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stone-700">{{ row.pk_size ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums text-stone-600">
                                    {{ rowCm(row) != null ? rowCm(row) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button
                    type="button"
                    class="mt-6 w-full min-h-12 rounded-2xl bg-stone-900 text-sm font-semibold text-white"
                    @click="chartOpen = false"
                >
                    Done
                </button>
            </div>
        </StoreBottomSheet>

        <!-- Mobile size sheet -->
        <StoreBottomSheet :open="sizeSheetOpen" title="Select size" z-class="z-[70]" @close="sizeSheetOpen = false">
            <div class="mt-4 space-y-4">
                <div class="inline-flex rounded-full bg-stone-100 p-1 ring-1 ring-stone-200">
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'uk' ? 'bg-white shadow-sm' : 'text-stone-600'"
                        @click="sizeSystem = 'uk'"
                    >
                        UK
                    </button>
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'eu' ? 'bg-white shadow-sm' : 'text-stone-600'"
                        @click="sizeSystem = 'eu'"
                    >
                        EU
                    </button>
                    <button
                        type="button"
                        class="min-h-10 rounded-full px-4 text-xs font-bold"
                        :class="sizeSystem === 'pk' ? 'bg-white shadow-sm' : 'text-stone-600'"
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
                                ? 'bg-stone-900 text-white'
                                : s.in_stock
                                  ? 'bg-stone-50 ring-1 ring-stone-200'
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
                    class="w-full min-h-12 rounded-2xl bg-stone-900 text-sm font-semibold text-white disabled:bg-stone-300"
                    :disabled="!canAddToCart"
                    @click="addToBag"
                >
                    Add to bag
                </button>
            </div>
        </StoreBottomSheet>
    </StoreLayout>
</template>
