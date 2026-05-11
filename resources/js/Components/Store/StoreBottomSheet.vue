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
                :class="['fixed inset-0 flex flex-col justify-end bg-stone-900/50 backdrop-blur-[2px]', zClass]"
                @click.self="emit('close')"
            >
                <Transition name="store-slide">
                    <div
                        v-if="open"
                        class="max-h-[min(90vh,720px)] w-full overflow-y-auto rounded-t-3xl bg-white shadow-2xl"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div class="sticky top-0 z-10 flex items-center justify-center bg-white/95 py-2 backdrop-blur-sm">
                            <span class="h-1.5 w-10 rounded-full bg-stone-200" aria-hidden="true" />
                        </div>
                        <div class="px-4 pb-8 pt-1 sm:px-6">
                            <h2 v-if="title" class="text-lg font-semibold tracking-tight text-stone-900">
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
