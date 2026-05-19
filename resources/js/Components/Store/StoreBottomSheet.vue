<script setup>
defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, default: '' },
    zClass: { type: String, default: 'z-[60]' },
});
const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <Transition name="store-fade">
            <div
                v-if="open"
                :class="['fixed inset-0 flex flex-col justify-end bg-stadium-ink/50 backdrop-blur-[2px]', zClass]"
                @click.self="emit('close')"
            >
                <Transition name="store-slide">
                    <div
                        v-if="open"
                        class="max-h-[min(90vh,720px)] w-full overflow-y-auto rounded-t-3xl bg-stadium-white shadow-2xl"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div class="sticky top-0 z-10 flex items-center justify-center border-b border-stadium-outline-soft bg-stadium-white py-2">
                            <span class="h-1.5 w-10 rounded-full bg-stadium-outline-soft" aria-hidden="true" />
                        </div>
                        <div class="px-4 pb-8 pt-1 sm:px-6">
                            <h2 v-if="title" class="font-display text-lg font-bold tracking-tight text-stadium-ink">
                                {{ title }}
                            </h2>
                            <slot />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.store-fade-enter-active,
.store-fade-leave-active {
    transition: opacity 0.2s ease;
}
.store-fade-enter-from,
.store-fade-leave-to {
    opacity: 0;
}
.store-slide-enter-active,
.store-slide-leave-active {
    transition:
        transform 0.28s cubic-bezier(0.32, 0.72, 0, 1),
        opacity 0.2s ease;
}
.store-slide-enter-from,
.store-slide-leave-to {
    opacity: 0.9;
    transform: translateY(100%);
}
</style>
