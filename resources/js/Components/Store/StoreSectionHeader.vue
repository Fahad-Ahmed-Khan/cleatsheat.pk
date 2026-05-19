<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="font-display text-2xl font-bold tracking-tight text-stadium-ink sm:text-[28px] lg:text-[32px]">
                {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-1 max-w-xl text-base leading-relaxed text-stadium-secondary">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.action" class="mt-1 shrink-0 sm:mt-0">
            <slot name="action" />
        </div>
    </div>
</template>
