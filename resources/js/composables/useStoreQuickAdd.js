import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** @param {{ in_stock?: boolean, stock_qty?: number | null }} size */
export function isSizeInStock(size) {
    if (size.in_stock === true) return true;
    if (size.in_stock === false) return false;
    const qty = size.stock_qty;
    if (qty === null || qty === undefined) return true;
    return qty > 0;
}

export function inStockSizes(variant) {
    return (variant?.sizes ?? []).filter(isSizeInStock);
}

/** @param {{ variants?: Array<{ is_active?: boolean }> }} product */
export function activeVariants(product) {
    return (product?.variants ?? []).filter((v) => v.is_active !== false);
}

/** First variant that has at least one size in stock, else first active variant. */
export function defaultQuickAddVariant(product) {
    const variants = activeVariants(product);
    return variants.find((v) => inStockSizes(v).length > 0) ?? variants[0] ?? null;
}

export function useStoreQuickAdd() {
    const analytics = useStoreAnalytics();
    const adding = ref(false);
    const sheetOpen = ref(false);
    const sheetProduct = ref(null);

    function closeSheet() {
        sheetOpen.value = false;
        sheetProduct.value = null;
    }

    function openSheet(product) {
        const variants = activeVariants(product);
        const hasSelectable = variants.some((v) => inStockSizes(v).length > 0);
        if (!hasSelectable) {
            router.visit(route('store.product', product.slug));
            return;
        }
        sheetProduct.value = product;
        sheetOpen.value = true;
    }

    function postAdd(product, variantId, sizeLabel, onSuccess) {
        if (adding.value) return;
        adding.value = true;
        router.post(
            route('store.cart.add'),
            {
                product_variant_id: variantId,
                size_label: sizeLabel,
                quantity: 1,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    analytics.trackAddToCart({
                        productId: product.id,
                        name: product.name,
                        price: Number(product.variants?.[0]?.price ?? 0),
                        quantity: 1,
                    });
                    closeSheet();
                    onSuccess?.();
                },
                onFinish: () => {
                    adding.value = false;
                },
            },
        );
    }

    function quickAdd(product) {
        const qa = product.quick_add;
        if (qa?.variant_id && qa?.size_label) {
            postAdd(product, qa.variant_id, qa.size_label);
            return;
        }

        const variants = activeVariants(product);
        if (variants.length === 1) {
            const v = variants[0];
            const sizes = inStockSizes(v);
            if (sizes.length === 1) {
                postAdd(product, v.id, sizes[0].size_label);
                return;
            }
            if (sizes.length > 1) {
                openSheet(product);
                return;
            }
        }

        if (variants.length > 1) {
            const stocked = variants.filter((v) => inStockSizes(v).length > 0);
            if (stocked.length === 1 && inStockSizes(stocked[0]).length === 1) {
                const v = stocked[0];
                postAdd(product, v.id, inStockSizes(v)[0].size_label);
                return;
            }
            openSheet(product);
            return;
        }

        const multiSizeOneVariant = variants.length === 1 && inStockSizes(variants[0]).length > 1;
        if (multiSizeOneVariant) {
            openSheet(product);
            return;
        }

        router.visit(route('store.product', product.slug));
    }

    function addWithSize(product, variantId, sizeLabel) {
        postAdd(product, variantId, sizeLabel);
    }

    return {
        adding,
        sheetOpen,
        sheetProduct,
        quickAdd,
        addWithSize,
        closeSheet,
        openSheet,
    };
}
