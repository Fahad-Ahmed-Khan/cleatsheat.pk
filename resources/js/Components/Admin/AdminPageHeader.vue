<script setup>
import AdminBreadcrumb from './AdminBreadcrumb.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1">{{ title }}</h4>
            <p v-if="subtitle" class="text-muted mb-0">{{ subtitle }}</p>
            <div v-if="breadcrumbs?.length" class="mt-2">
                <AdminBreadcrumb :items="breadcrumbs" />
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

