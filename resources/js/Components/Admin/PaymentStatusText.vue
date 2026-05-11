<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const meta = computed(() => {
    const s = String(props.status || '').toLowerCase();
    if (s === 'paid') return { cls: 'text-success', dot: 'bg-success', label: 'Paid' };
    if (s === 'pending') return { cls: 'text-warning', dot: 'bg-warning', label: 'Pending' };
    if (s === 'failed') return { cls: 'text-danger', dot: 'bg-danger', label: 'Failed' };
    if (s === 'canceled' || s === 'cancelled') return { cls: 'text-secondary', dot: 'bg-secondary', label: 'Cancelled' };
    if (s === 'refunded') return { cls: 'text-info', dot: 'bg-info', label: 'Refunded' };
    return { cls: 'text-muted', dot: 'bg-secondary', label: props.status };
});
</script>

<template>
    <span class="d-inline-flex align-items-center gap-2 fw-semibold" :class="meta.cls">
        <span class="rounded-circle" :class="meta.dot" style="width: 10px; height: 10px;"></span>
        <span>{{ meta.label }}</span>
    </span>
</template>

