<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useStoreFormat } from '@/composables/useStoreFormat';

const { formatPKR } = useStoreFormat();

const props = defineProps({
    product: { type: Object, required: true },
    /** subtle entrance delay for staggered lists */
    index: { type: Number, default: 0 },
});

function primaryImage(p) {
    return p.images?.[0]?.path ?? null;
}

const variant0 = computed(() => props.product.variants?.[0]);

const hasSale = computed(
    () =>
        variant0.value?.compare_at_price != null &&
        Number(variant0.value.compare_at_price) > Number(variant0.value?.price ?? 0),
);

const categoryLabel = computed(() => props.product.category?.name ?? '');
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-3xl border border-stadium-outline-soft/30 bg-stadium-white stadium-ambient-shadow transition duration-200 hover:-translate-y-1 hover:shadow-stadium-lg"
        :style="{ animationDelay: `${Math.min(props.index * 40, 400)}ms` }"
    >
        <Link :href="route('store.product', product.slug)" class="block min-h-0 flex-1">
            <div class="relative aspect-square overflow-hidden bg-stadium-muted p-4">
                <span
                    v-if="hasSale"
                    class="absolute left-3 top-3 z-10 rounded-md bg-stadium-ink px-2 py-1 font-display text-[10px] font-bold uppercase tracking-wider text-stadium-lime"
                >
                    Sale
                </span>
                <span
                    v-else-if="categoryLabel"
                    class="absolute left-3 top-3 z-10 max-w-[70%] truncate rounded-md bg-stadium-lime px-2 py-1 font-display text-[10px] font-bold uppercase tracking-wider text-stadium-lime-ink"
                >
                    {{ categoryLabel }}
                </span>
                <img
                    v-if="primaryImage(product)"
                    :src="primaryImage(product)"
                    :alt="product.name"
                    class="h-full w-full object-contain transition duration-500 ease-out group-hover:scale-105"
                    loading="lazy"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center text-xs text-stadium-secondary"
                >
                    Photo soon
                </div>
            </div>
            <div class="flex flex-1 flex-col p-4">
                <p class="font-display text-[11px] font-bold uppercase tracking-wide text-stadium-secondary">
                    {{ product.brand?.name }}
                </p>
                <h3 class="mt-1 line-clamp-2 min-h-[2.6rem] font-display text-sm font-bold leading-snug text-stadium-ink">
                    {{ product.name }}
                </h3>
                <div class="mt-auto flex items-baseline gap-2 pt-3">
                    <p v-if="product.variants?.length" class="font-display text-lg font-bold tabular-nums text-stadium-ink">
                        {{ formatPKR(product.variants[0].price) }}
                    </p>
                    <p
                        v-if="hasSale"
                        class="text-xs tabular-nums text-stadium-secondary line-through"
                    >
                        {{ formatPKR(variant0.compare_at_price) }}
                    </p>
                </div>
            </div>
        </Link>
    </article>
</template>
