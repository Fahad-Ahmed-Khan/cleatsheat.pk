<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    href: { type: String, default: '' },
});
</script>

<template>
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">{{ title }}</div>
                    <div class="h4 mb-0">{{ value }}</div>
                </div>
                <div class="text-muted">
                    <slot name="icon" />
                </div>
            </div>
            <div v-if="href" class="mt-3">
                <Link :href="href" class="small text-decoration-none">
                    View
                </Link>
            </div>
        </div>
    </div>
</template>

