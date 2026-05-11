<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-0">{{ title }}</h5>
                <div v-if="description" class="text-muted small mt-1">{{ description }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <slot name="headerActions" />
            </div>
        </div>
        <div class="card-body">
            <slot />
        </div>
        <div v-if="$slots.actions" class="card-footer d-flex justify-content-end gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

