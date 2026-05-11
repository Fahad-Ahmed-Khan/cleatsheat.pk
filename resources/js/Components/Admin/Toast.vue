<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    toasts: { type: Array, required: true },
});

const emit = defineEmits(['dismiss']);

const containerRef = ref(null);

const ariaLive = computed(() => (props.toasts.length ? 'polite' : 'off'));

function dismiss(id) {
    emit('dismiss', id);
}

onMounted(() => {
    // noop: kept to ensure component is mounted before Bootstrap toast JS is later added
});

watch(
    () => props.toasts,
    () => {
        // noop: Bootstrap toast JS could be wired here later if desired
    },
    { deep: true },
);
</script>

<template>
    <div
        ref="containerRef"
        class="toast-container position-fixed top-0 end-0 p-3"
        role="status"
        :aria-live="ariaLive"
        aria-atomic="true"
        style="z-index: 1090"
    >
        <div
            v-for="t in toasts"
            :key="t.id"
            class="toast show align-items-center text-bg-dark border-0 mb-2"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
        >
            <div class="d-flex">
                <div class="toast-body">
                    {{ t.message }}
                </div>
                <button
                    type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    aria-label="Close"
                    @click="dismiss(t.id)"
                />
            </div>
        </div>
    </div>
</template>

