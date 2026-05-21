<script setup>
defineProps({
    label: { type: String, required: true },
    forId: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label :for="forId" class="text-label text-stadium-secondary">{{ label }}</label>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-xs text-stadium-secondary">{{ hint }}</p>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
