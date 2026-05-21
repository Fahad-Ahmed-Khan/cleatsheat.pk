<script setup>
defineProps({
    class: { type: String, default: '' },
});
</script>

<template>
    <div
        class="animate-pulse rounded-lg bg-stadium-container-high"
        :class="$props.class"
        role="status"
        aria-busy="true"
        aria-label="Loading"
    />
</template>
