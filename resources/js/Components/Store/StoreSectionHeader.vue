<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-stone-900 sm:text-xl">
                {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-1 max-w-xl text-sm text-stone-500">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.action" class="mt-2 shrink-0 sm:mt-0">
            <slot name="action" />
        </div>
    </div>
</template>
