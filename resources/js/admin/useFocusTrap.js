import { onMounted, onBeforeUnmount, watch } from 'vue';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * useFocusTrap — small accessibility helper for ad-hoc admin modals.
 *
 * - Traps Tab / Shift+Tab inside `containerRef` while `isOpen` is truthy.
 * - Calls `onClose` on Escape.
 * - Restores focus to the previously focused element on close/unmount.
 *
 * Usage:
 *   const dialogRef = ref(null);
 *   useFocusTrap(dialogRef, computed(() => isOpen.value), () => closeFn());
 */
export function useFocusTrap(containerRef, isOpen, onClose) {
    let lastFocused = null;

    function getFocusable() {
        const root = containerRef.value;
        if (!root) return [];
        return Array.from(root.querySelectorAll(FOCUSABLE_SELECTOR))
            .filter((el) => !el.hasAttribute('disabled') && el.offsetParent !== null);
    }

    function handleKeydown(event) {
        if (!isOpen.value) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            onClose?.();
            return;
        }
        if (event.key !== 'Tab') return;

        const focusables = getFocusable();
        if (focusables.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function focusFirst() {
        const focusables = getFocusable();
        if (focusables.length > 0) {
            focusables[0].focus();
        } else if (containerRef.value) {
            containerRef.value.focus?.();
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown, true);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', handleKeydown, true);
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    });

    watch(
        () => isOpen.value,
        (open) => {
            if (open) {
                lastFocused = document.activeElement;
                requestAnimationFrame(focusFirst);
            } else if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
                lastFocused = null;
            }
        },
        { immediate: true },
    );
}
