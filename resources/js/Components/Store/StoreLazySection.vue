<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Renders its slot only once it scrolls near the viewport. Combined with
 * defineAsyncComponent children, this keeps below-the-fold section JS off the
 * initial render path (smaller main chunk, less mount work) without hurting CLS:
 * a reserved min-height holds space until the real content swaps in.
 */
const props = defineProps({
    minHeight: { type: String, default: '320px' },
    rootMargin: { type: String, default: '400px 0px' },
});

const visible = ref(false);
const root = ref(null);
let observer = null;

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        visible.value = true;
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                visible.value = true;
                observer?.disconnect();
                observer = null;
            }
        },
        { rootMargin: props.rootMargin },
    );

    if (root.value) {
        observer.observe(root.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});
</script>

<template>
    <div ref="root" :style="visible ? null : { minHeight }">
        <slot v-if="visible" />
    </div>
</template>
