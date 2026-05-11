<script setup>
import { Link } from '@inertiajs/vue3';
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
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200/80 transition duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-stone-300/80"
        :style="{ animationDelay: `${Math.min(index * 40, 400)}ms` }"
    >
        <Link :href="route('store.product', product.slug)" class="block min-h-0 flex-1">
            <div class="relative aspect-[4/5] overflow-hidden bg-stone-100">
                <img
                    v-if="primaryImage(product)"
                    :src="primaryImage(product)"
                    :alt="product.name"
                    class="h-full w-full object-cover transition duration-300 ease-out group-hover:scale-[1.03]"
                    loading="lazy"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center text-xs text-stone-400"
                >
                    Photo soon
                </div>
            </div>
            <div class="flex flex-1 flex-col p-3 sm:p-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-stone-500">
                    {{ product.brand?.name }}
                </p>
                <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-stone-900">
                    {{ product.name }}
                </h3>
                <div class="mt-auto pt-3 flex items-baseline gap-2">
                    <p v-if="product.variants?.length" class="text-base font-semibold tabular-nums text-stone-900">
                        {{ formatPKR(product.variants[0].price) }}
                    </p>
                    <p
                        v-if="product.variants?.[0]?.compare_at_price"
                        class="text-xs tabular-nums text-stone-400 line-through"
                    >
                        {{ formatPKR(product.variants[0].compare_at_price) }}
                    </p>
                </div>
            </div>
        </Link>
    </article>
</template>
