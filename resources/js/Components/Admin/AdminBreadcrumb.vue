<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] }, // [{ label, href? }]
});
</script>

<template>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li
                v-for="(item, idx) in items"
                :key="`${idx}-${item.label}`"
                class="breadcrumb-item"
                :class="{ active: !item.href }"
                :aria-current="!item.href ? 'page' : null"
            >
                <Link v-if="item.href" :href="item.href">{{ item.label }}</Link>
                <span v-else>{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>

