<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <StoreLayout>
        <div class="store-container flex min-h-[60vh] items-center justify-center py-12 pb-28 md:pb-16">
            <div class="w-full max-w-md rounded-2xl bg-stadium-white p-8 shadow-stadium ring-1 ring-stadium-outline-soft/50">
                <h1 class="text-display-md text-stadium-ink">{{ title }}</h1>
                <p v-if="subtitle" class="mt-2 text-body-lg text-stadium-secondary">{{ subtitle }}</p>
                <div class="mt-8">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="mt-6 border-t border-stadium-outline-soft/50 pt-6 text-center text-sm text-stadium-secondary">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
