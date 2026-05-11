<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './ProductForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    brands: { type: Array, required: true },
    categories: { type: Array, required: true },
    colors: { type: Array, required: true },
    size_charts: { type: Array, required: true },
    enums: { type: Object, required: true },
});
</script>

<template>
    <Head title="Admin — New product" />
    <AdminLayout>
        <ProductForm
            :brands="brands"
            :categories="categories"
            :colors="colors"
            :size_charts="size_charts"
            :enums="enums"
        />
    </AdminLayout>
</template>
