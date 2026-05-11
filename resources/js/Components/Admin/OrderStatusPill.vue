<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const label = computed(() => {
    const s = String(props.status || '').toLowerCase();
    const map = {
        pending: 'Pending',
        confirmed: 'Confirmed',
        processing: 'Processing',
        shipped: 'Shipped',
        delivered: 'Delivered',
        cancelled: 'Cancelled',
        canceled: 'Cancelled',
    };
    return map[s] ?? props.status;
});
</script>

<template>
    <span class="badge bg-label-primary">{{ label }}</span>
</template>

