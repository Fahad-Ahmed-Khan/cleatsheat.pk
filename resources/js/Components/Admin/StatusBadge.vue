<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const variant = computed(() => {
    const s = String(props.status || '').toLowerCase();
    if (['paid', 'active', 'enabled', 'delivered', 'booked', 'success', 'completed'].includes(s)) return 'success';
    if (['pending', 'processing', 'in_progress', 'confirmed', 'shipped', 'in_transit', 'out_for_delivery', 'ready_to_pickup'].includes(s)) return 'warning';
    if (['cancelled', 'canceled', 'failed', 'disabled', 'inactive', 'error', 'refunded'].includes(s)) return 'danger';
    return 'secondary';
});
</script>

<template>
    <span class="badge" :class="`bg-label-${variant}`">{{ status }}</span>
</template>

