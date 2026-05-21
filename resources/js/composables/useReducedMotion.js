import { onMounted, onUnmounted, ref } from 'vue';

export function useReducedMotion() {
    const prefersReducedMotion = ref(false);
    let mq = null;

    function update() {
        prefersReducedMotion.value = mq?.matches ?? false;
    }

    onMounted(() => {
        mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        update();
        mq.addEventListener('change', update);
    });

    onUnmounted(() => {
        mq?.removeEventListener('change', update);
    });

    return { prefersReducedMotion };
}
