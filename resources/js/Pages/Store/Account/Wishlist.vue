<script setup>
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    seo: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

function goToPage(page) {
    router.get(route('store.account.wishlist'), { page }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="Wishlist">
        <p v-if="!products.length" class="text-sm text-stadium-secondary">
            Your wishlist is empty.
            <Link :href="route('store.shop')" class="font-semibold text-store-primary underline">Browse products</Link>
        </p>
        <div v-else class="store-product-grid">
            <StoreProductCard v-for="p in products" :key="p.id" :product="p" />
        </div>
        <nav
            v-if="pagination.last_page > 1"
            class="mt-8 flex items-center justify-center gap-4 text-sm"
        >
            <button
                type="button"
                class="font-semibold text-stadium-ink disabled:opacity-40"
                :disabled="pagination.current_page <= 1"
                @click="goToPage(pagination.current_page - 1)"
            >
                Previous
            </button>
            <span class="text-stadium-secondary">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button
                type="button"
                class="font-semibold text-stadium-ink disabled:opacity-40"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="goToPage(pagination.current_page + 1)"
            >
                Next
            </button>
        </nav>
    </StoreAccountLayout>
</template>
