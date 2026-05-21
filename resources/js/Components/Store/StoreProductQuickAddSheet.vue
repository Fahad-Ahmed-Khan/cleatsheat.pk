<script setup>
import StoreBottomSheet from '@/Components/Store/StoreBottomSheet.vue';
import {
    activeVariants,
    defaultQuickAddVariant,
    inStockSizes,
} from '@/composables/useStoreQuickAdd';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    product: { type: Object, default: null },
    adding: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'select']);

const selectedVariantId = ref(null);

watch(
    () => props.product,
    (product) => {
        selectedVariantId.value = defaultQuickAddVariant(product)?.id ?? null;
    },
    { immediate: true },
);

const variants = computed(() => activeVariants(props.product));

const hasMultipleVariants = computed(() => variants.value.length > 1);

const selectedVariant = computed(() => {
    const list = variants.value;
    if (!list.length) return null;
    const picked = list.find((v) => v.id === selectedVariantId.value);
    if (picked && inStockSizes(picked).length > 0) return picked;
    return defaultQuickAddVariant(props.product);
});

const sizes = computed(() => inStockSizes(selectedVariant.value));

const variantsWithStock = computed(() =>
    variants.value.filter((v) => inStockSizes(v).length > 0),
);

function selectVariant(variantId) {
    selectedVariantId.value = variantId;
}
</script>

<template>
    <StoreBottomSheet :open="open" title="Select size" @close="emit('close')">
        <template v-if="product">
            <p class="mb-4 text-sm text-stadium-secondary">
                {{ product.name }}
            </p>

            <template v-if="hasMultipleVariants">
                <p class="text-xs font-semibold uppercase tracking-wide text-stadium-secondary">Colour</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="v in variantsWithStock"
                        :key="v.id"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition active:scale-[0.98]"
                        :class="
                            selectedVariant?.id === v.id
                                ? 'bg-store-primary text-stadium-ink ring-2 ring-store-primary/30'
                                : 'bg-stadium-muted text-stadium-ink hover:bg-stadium-outline-soft/40'
                        "
                        @click="selectVariant(v.id)"
                    >
                        {{ v.color?.name || 'Default' }}
                    </button>
                </div>
                <p
                    v-if="variantsWithStock.length < variants.length"
                    class="mt-2 text-xs text-stadium-secondary"
                >
                    Some colours are out of stock and hidden.
                </p>
            </template>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-stadium-secondary">Size</p>
            <div v-if="sizes.length" class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="s in sizes"
                    :key="`${selectedVariant?.id}-${s.size_label}`"
                    type="button"
                    class="min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-5 py-2 text-sm font-bold text-stadium-ink transition hover:border-store-primary hover:ring-2 hover:ring-store-primary/20 active:scale-[0.98] disabled:opacity-50"
                    :disabled="adding"
                    @click="
                        emit('select', {
                            variantId: selectedVariant?.id,
                            sizeLabel: s.size_label,
                        })
                    "
                >
                    {{ s.uk_size ? `UK ${s.uk_size}` : s.size_label }}
                </button>
            </div>
            <div v-else class="mt-4">
                <p class="text-sm text-stadium-secondary">No sizes in stock right now.</p>
                <Link
                    :href="route('store.product', product.slug)"
                    class="mt-3 inline-flex text-sm font-bold text-stadium-olive hover:underline"
                    @click="emit('close')"
                >
                    View product details
                </Link>
            </div>
        </template>
    </StoreBottomSheet>
</template>
