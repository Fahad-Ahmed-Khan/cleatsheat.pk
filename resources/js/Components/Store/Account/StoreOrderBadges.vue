<script setup>
import { orderStatusBadge, paymentStatusBadge } from '@/composables/useStoreOrderBadges';
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    paymentStatus: { type: String, default: '' },
});

const orderBadge = computed(() => orderStatusBadge(props.status));
const paymentBadge = computed(() => paymentStatusBadge(props.paymentStatus));
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span v-if="status" :class="orderBadge.class">{{ orderBadge.label }}</span>
        <span v-if="paymentStatus" :class="paymentBadge.class">{{ paymentBadge.label }}</span>
    </div>
</template>
