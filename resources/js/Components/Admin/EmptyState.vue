<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="text-center py-5">
        <div class="mb-3">
            <slot name="icon" />
        </div>
        <h5 class="mb-2">{{ title }}</h5>
        <p v-if="description" class="text-muted mb-4">{{ description }}</p>
        <slot name="actions" />
    </div>
</template>

