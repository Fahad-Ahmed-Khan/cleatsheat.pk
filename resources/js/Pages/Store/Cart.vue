<script setup>
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreTrustStrip from '@/Components/Store/StoreTrustStrip.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { useStoreFormat } from '@/composables/useStoreFormat';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    lines: {
        type: Array,
        default: () => [],
    },
    subtotal: {
        type: Number,
        required: true,
    },
    seo: {
        type: Object,
        required: true,
    },
});

const { formatPKR } = useStoreFormat();

function updateQty(line, delta) {
    const next = line.quantity + delta;
    if (next < 1) {
        remove(line);
        return;
    }
    router.patch(
        route('store.cart.items.update', line.id),
        { quantity: Math.min(10, next) },
        { preserveScroll: true },
    );
}

function setQty(line, event) {
    const qty = Number(event.target.value);
    router.patch(
        route('store.cart.items.update', line.id),
        { quantity: qty },
        { preserveScroll: true },
    );
}

function remove(line) {
    router.delete(route('store.cart.items.destroy', line.id));
}
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-lg pb-36 px-4 pt-8 sm:max-w-3xl sm:pb-12 lg:px-6">
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900">
                Bag
            </h1>
            <p class="mt-1 text-sm text-stone-500">
                Review sizes before checkout — shoe orders ship fast from Karachi &amp; Lahore hubs.
            </p>

            <div v-if="!lines.length" class="mt-12 rounded-3xl bg-white px-6 py-14 text-center shadow-sm ring-1 ring-stone-200">
                <p class="text-stone-600">
                    Nothing here yet — your next pair is one browse away.
                </p>
                <Link
                    :href="route('store.home')"
                    class="mt-8 inline-flex min-h-12 items-center justify-center rounded-full bg-stone-900 px-8 text-sm font-semibold text-white shadow-md transition active:scale-[0.98]"
                >
                    Continue shopping
                </Link>
            </div>

            <div v-else class="mt-8 space-y-4">
                <article
                    v-for="line in lines"
                    :key="line.id"
                    class="flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200/90"
                >
                    <Link
                        :href="route('store.product', line.product.slug)"
                        class="h-28 w-24 shrink-0 overflow-hidden rounded-xl bg-stone-100 ring-1 ring-stone-100"
                    >
                        <img
                            v-if="line.product.image"
                            :src="line.product.image"
                            class="h-full w-full object-cover"
                            :alt="line.product.name"
                        >
                    </Link>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <Link
                            :href="route('store.product', line.product.slug)"
                            class="truncate font-semibold text-stone-900 hover:underline"
                        >
                            {{ line.product.name }}
                        </Link>
                        <p class="mt-1 text-xs text-stone-500">
                            {{ line.variant.color }} · {{ line.size_label }}
                        </p>
                        <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                            <div class="inline-flex items-center rounded-full bg-stone-100 p-1 ring-1 ring-stone-200/80">
                                <button
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-full text-lg font-semibold text-stone-700 transition hover:bg-white active:scale-95"
                                    aria-label="Decrease quantity"
                                    @click="updateQty(line, -1)"
                                >
                                    −
                                </button>
                                <span class="min-w-[2rem] text-center text-sm font-semibold tabular-nums">{{ line.quantity }}</span>
                                <button
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-full text-lg font-semibold text-stone-700 transition hover:bg-white active:scale-95"
                                    aria-label="Increase quantity"
                                    @click="updateQty(line, 1)"
                                >
                                    +
                                </button>
                            </div>
                            <button
                                type="button"
                                class="text-xs font-semibold text-red-600 underline underline-offset-2"
                                @click="remove(line)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-base font-semibold tabular-nums text-stone-900">
                            {{ formatPKR(line.line_total) }}
                        </p>
                        <label class="sr-only">Quantity</label>
                        <select
                            class="mt-2 hidden rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs sm:block"
                            :value="line.quantity"
                            @change="setQty(line, $event)"
                        >
                            <option v-for="n in 10" :key="n" :value="n">
                                {{ n }}
                            </option>
                        </select>
                    </div>
                </article>

                <StoreTrustStrip compact class="mt-8" />
            </div>
        </div>

        <!-- Mobile sticky checkout -->
        <div
            v-if="lines.length"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200/90 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md sm:hidden"
        >
            <div class="mx-auto flex max-w-lg items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-stone-500">
                        Subtotal
                    </p>
                    <p class="text-xl font-semibold tabular-nums text-stone-900">
                        {{ formatPKR(subtotal) }}
                    </p>
                </div>
                <Link
                    :href="route('store.checkout')"
                    class="inline-flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-stone-900 text-center text-sm font-semibold text-white shadow-lg transition active:scale-[0.98]"
                >
                    Checkout
                </Link>
            </div>
        </div>

        <!-- Desktop summary -->
        <div
            v-if="lines.length"
            class="mx-auto hidden max-w-3xl flex-col gap-4 px-6 pb-16 sm:flex sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-stone-600">
                Subtotal <span class="font-semibold tabular-nums text-stone-900">{{ formatPKR(subtotal) }}</span>
            </p>
            <Link
                :href="route('store.checkout')"
                class="inline-flex min-h-12 items-center justify-center rounded-full bg-stone-900 px-10 text-sm font-semibold text-white shadow-md transition hover:bg-stone-800"
            >
                Checkout
            </Link>
        </div>
    </StoreLayout>
</template>
