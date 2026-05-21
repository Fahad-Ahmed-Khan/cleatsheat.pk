import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Returns true when header should be hidden (scrolling down past threshold).
 */
export function useScrollDirection(threshold = 64) {
    const headerHidden = ref(false);
    let lastY = 0;
    let ticking = false;

    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = window.scrollY;
            if (y < threshold) {
                headerHidden.value = false;
            } else if (y > lastY + 8) {
                headerHidden.value = true;
            } else if (y < lastY - 8) {
                headerHidden.value = false;
            }
            lastY = y;
            ticking = false;
        });
    }

    onMounted(() => {
        lastY = window.scrollY;
        window.addEventListener('scroll', onScroll, { passive: true });
    });

    onUnmounted(() => {
        window.removeEventListener('scroll', onScroll);
    });

    return { headerHidden };
}
