<script setup>

import { useStoreFormat } from '@/composables/useStoreFormat';

import { useStoreWishlist } from '@/composables/useStoreWishlist';

import { Link } from '@inertiajs/vue3';

import { motion } from 'motion-v';

import { computed, inject, ref } from 'vue';

import { useReducedMotion } from '@/composables/useReducedMotion';
import { isSizeInStock } from '@/composables/useStoreQuickAdd';



const { formatPKR } = useStoreFormat();

const { toggle, isWishlisted } = useStoreWishlist();

const quickAddApi = inject('storeQuickAdd', null);

const { prefersReducedMotion } = useReducedMotion();



const props = defineProps({

    product: { type: Object, required: true },

    index: { type: Number, default: 0 },

});



const imageHover = ref(false);



const cardImageSizes = '(min-width: 1024px) 23vw, (min-width: 640px) 31vw, 48vw';

function primaryImage(p) {

    return p.images?.[0]?.path ?? null;

}

function primarySrcset(p) {

    return p.images?.[0]?.srcset ?? null;

}

function hoverImage(p) {

    return p.images?.[1]?.path ?? null;

}

function hoverSrcset(p) {

    return p.images?.[1]?.srcset ?? null;

}



const variant0 = computed(() => props.product.variants?.[0]);



const hasSale = computed(

    () =>

        variant0.value?.compare_at_price != null &&

        Number(variant0.value.compare_at_price) > Number(variant0.value?.price ?? 0),

);



function shortUkFromLabel(sizeLabel) {

    if (!sizeLabel) return '';

    return String(sizeLabel).replace(/^UK\s*/i, '').trim();

}



function formatCardSizeLine(s) {
    const uk = (s.uk_size && String(s.uk_size).trim()) || shortUkFromLabel(s.size_label);
    const eu = s.eu_size && String(s.eu_size).trim();
    if (uk && eu) return `UK ${uk} | EU ${eu}`;
    if (uk) return `UK ${uk}`;
    if (eu) return `EU ${eu}`;
    return s.size_label ? String(s.size_label) : '';
}

/** Full size line for price row, e.g. UK 7 | EU 40 | 25 cm */
function formatPriceSizeDetail(s) {
    const parts = [];
    const uk = (s.uk_size && String(s.uk_size).trim()) || shortUkFromLabel(s.size_label);
    const eu = s.eu_size && String(s.eu_size).trim();
    const pk = s.pk_size && String(s.pk_size).trim();
    if (uk) parts.push(`UK ${uk}`);
    if (eu) parts.push(`EU ${eu}`);
    if (pk) {
        parts.push(/cm/i.test(pk) ? pk : `${pk} cm`);
    } else if (s.size_label && !uk && !eu) {
        parts.push(String(s.size_label));
    }
    return parts.join(' | ');
}

const availableSizes = computed(() => {
    const seen = new Map();
    for (const variant of props.product.variants ?? []) {
        if (variant.is_active === false) continue;
        for (const s of variant.sizes ?? []) {
            if (!isSizeInStock(s)) continue;
            const key =
                (s.uk_size && String(s.uk_size).trim()) ||
                (s.size_label && String(s.size_label).trim()) ||
                (s.eu_size && `eu:${s.eu_size}`);
            if (!key || seen.has(key)) continue;
            seen.set(key, s);
        }
    }
    const rows = [...seen.values()];
    rows.sort((a, b) => {
        const af = parseFloat(a.uk_size);
        const bf = parseFloat(b.uk_size);
        if (!Number.isNaN(af) && !Number.isNaN(bf)) return af - bf;
        return String(a.uk_size || a.size_label || '').localeCompare(String(b.uk_size || b.size_label || ''));
    });
    return rows;
});

const primarySize = computed(() => availableSizes.value[0] ?? null);
const primarySizeTag = computed(() => (primarySize.value ? formatCardSizeLine(primarySize.value) : ''));
const primarySizeDetail = computed(() => (primarySize.value ? formatPriceSizeDetail(primarySize.value) : ''));
const extraSizeCount = computed(() => Math.max(0, availableSizes.value.length - 1));

const conditionKind = computed(() => props.product.card_condition_kind ?? 'used');
const conditionBadge = computed(
    () => props.product.card_condition_badge || (conditionKind.value === 'new' ? 'Brand New' : 'Pre-Loved'),
);
const isBrandNew = computed(() => conditionKind.value === 'new');

const cardMotion = computed(() =>

    prefersReducedMotion.value

        ? {}

        : {

              whileHover: { y: -4 },

              transition: { type: 'spring', stiffness: 400, damping: 28 },

          },

);



function onWishlistClick(e) {

    e.preventDefault();

    e.stopPropagation();

    toggle(props.product.id);

}



function onQuickAdd(e) {

    e.preventDefault();

    e.stopPropagation();

    quickAddApi?.quickAdd?.(props.product);

}

</script>



<template>

    <motion.article

        v-bind="cardMotion"

        class="group relative flex flex-col overflow-hidden rounded-3xl border border-stadium-outline-soft/30 bg-stadium-white stadium-ambient-shadow transition-shadow duration-200 hover:shadow-stadium-lg md:hover:-translate-y-0"

        :style="{ animationDelay: `${Math.min(props.index * 40, 400)}ms` }"

    >

        <div class="relative aspect-[4/5] overflow-hidden bg-stadium-muted">

            <Link :href="route('store.product', product.slug)" class="block h-full w-full" @click.stop>

                <img

                    v-if="primaryImage(product)"

                    :src="primaryImage(product)"

                    :srcset="primarySrcset(product) || undefined"

                    :sizes="primarySrcset(product) ? cardImageSizes : undefined"

                    :width="product.images?.[0]?.width || undefined"

                    :height="product.images?.[0]?.height || undefined"

                    :alt="product.name"

                    class="absolute inset-0 h-full w-full object-cover transition duration-500 ease-out"

                    :class="hoverImage(product) && imageHover ? 'opacity-0' : 'opacity-100 group-hover:scale-[1.03]'"

                    loading="lazy"

                    decoding="async"

                    @mouseenter="imageHover = true"

                    @mouseleave="imageHover = false"

                />

                <img

                    v-if="hoverImage(product)"

                    :src="hoverImage(product)"

                    :srcset="hoverSrcset(product) || undefined"

                    :sizes="hoverSrcset(product) ? cardImageSizes : undefined"

                    :width="product.images?.[1]?.width || undefined"

                    :height="product.images?.[1]?.height || undefined"

                    :alt="`${product.name} alternate view`"

                    class="absolute inset-0 h-full w-full object-cover transition duration-500 ease-out"

                    :class="imageHover ? 'opacity-100 scale-[1.03]' : 'opacity-0'"

                    loading="lazy"

                    decoding="async"

                    @mouseenter="imageHover = true"

                    @mouseleave="imageHover = false"

                />

                <div

                    v-if="!primaryImage(product)"

                    class="flex h-full w-full items-center justify-center text-xs text-stadium-secondary"

                >

                    Photo soon

                </div>

            </Link>



            <div class="pointer-events-none absolute left-3 top-3 z-10 flex max-w-[calc(100%-3.5rem)] flex-col items-start gap-1.5">
                <span
                    class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider shadow-sm"
                    :class="
                        isBrandNew
                            ? 'bg-stadium-lime text-stadium-lime-ink ring-1 ring-stadium-lime/80'
                            : 'bg-stadium-container-high text-stadium-ink ring-1 ring-stadium-outline-soft/50 dark:bg-stadium-dim dark:text-stadium-inverse-text'
                    "
                >
                    {{ conditionBadge }}
                </span>
                <div class="flex flex-wrap items-center gap-1.5">
                <span
                    v-if="product.card_surface_label"
                    class="rounded-md bg-stadium-inverse px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-stadium-inverse-text"
                >
                    {{ product.card_surface_label }}
                </span>
                <span
                    v-if="product.category?.name && !product.card_surface_label"
                    class="rounded-md bg-stadium-inverse px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-stadium-inverse-text"
                >
                    {{ product.category.name }}
                </span>
                <span
                    v-if="primarySizeTag"
                    class="rounded-md bg-store-primary px-2 py-0.5 text-[10px] font-bold tabular-nums text-store-primary-fg ring-1 ring-store-primary/30"
                >
                    {{ primarySizeTag }}<span v-if="extraSizeCount" class="ml-0.5 opacity-90">+{{ extraSizeCount }}</span>
                </span>
                <span
                    v-if="hasSale"
                    class="rounded-md bg-store-primary px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-primary-fg shadow-sm"
                >
                    Sale
                </span>
                </div>
            </div>

            <span
                v-if="product.card_authenticity_label"
                class="pointer-events-none absolute bottom-3 right-14 z-10 max-w-[40%] rounded-md bg-stadium-white/95 px-2 py-0.5 text-right text-[9px] font-semibold leading-tight text-stadium-olive shadow-sm ring-1 ring-stadium-outline-soft/60"
            >
                {{ product.card_authenticity_label }}
            </span>

            <button
                type="button"
                class="absolute right-3 top-3 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-stadium-white/90 text-stadium-secondary shadow-sm transition hover:text-red-500 focus-visible:ring-2 focus-visible:ring-store-primary"
                :aria-label="isWishlisted(product.id) ? 'Remove from wishlist' : 'Add to wishlist'"
                :aria-pressed="isWishlisted(product.id)"
                @click="onWishlistClick"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    :fill="isWishlisted(product.id) ? 'currentColor' : 'none'"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path
                        d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                    />
                </svg>
            </button>

            <button
                v-if="quickAddApi"
                type="button"
                class="absolute bottom-3 right-3 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-stadium-lime text-stadium-lime-ink shadow-md ring-1 ring-stadium-lime/50 transition-all duration-200 hover:bg-stadium-lime/90 active:scale-95 focus-visible:ring-2 focus-visible:ring-store-primary max-md:opacity-100 md:translate-y-1 md:opacity-0 md:pointer-events-none md:group-hover:translate-y-0 md:group-hover:opacity-100 md:group-hover:pointer-events-auto"
                :aria-label="`Add ${product.name} to bag`"
                @click="onQuickAdd"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M6 7h15l-1.5 11h-12L6 7z" stroke-linejoin="round" />
                    <path d="M9 7V5a3 3 0 116 0v2" stroke-linecap="round" />
                </svg>
            </button>

        </div>



        <Link :href="route('store.product', product.slug)" class="flex flex-1 flex-col p-3 md:p-4">

            <h3 class="line-clamp-2 font-display text-sm font-bold leading-snug text-stadium-ink dark:text-white">

                {{ product.name }}

            </h3>

            <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p v-if="product.variants?.length" class="font-display text-lg font-bold tabular-nums text-stadium-ink dark:text-white">
                    {{ formatPKR(product.variants[0].price) }}
                </p>
                <p
                    v-if="hasSale"
                    class="text-xs tabular-nums text-stadium-secondary line-through dark:text-stadium-inverse-text/70"
                >
                    {{ formatPKR(variant0.compare_at_price) }}
                </p>
                <p
                    v-if="primarySizeDetail"
                    class="text-[10px] font-medium leading-tight text-stadium-secondary"
                >
                    {{ primarySizeDetail }}<span v-if="extraSizeCount" class="text-stadium-outline"> · +{{ extraSizeCount }}</span>
                </p>
            </div>

        </Link>

    </motion.article>

</template>


