import { onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useFlash() {
    const page = usePage();
    const toasts = ref([]);

    function pushToast({ message, variant = 'info' }) {
        if (!message) return;
        toasts.value.push({
            id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
            message,
            variant,
        });
    }

    function consumeFlashProps() {
        const flash = page.props.flash || {};
        if (flash.success) pushToast({ message: flash.success, variant: 'success' });
        if (flash.error) pushToast({ message: flash.error, variant: 'danger' });
        if (flash.status) pushToast({ message: flash.status, variant: 'info' });

        // Existing app already shares this key (used for checkout); keep compatible.
        if (page.props.flashPaymentError) pushToast({ message: page.props.flashPaymentError, variant: 'danger' });
    }

    onMounted(() => {
        consumeFlashProps();
    });

    watch(
        () => page.props,
        () => consumeFlashProps(),
        { deep: true },
    );

    function dismiss(id) {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    return { toasts, dismiss, pushToast };
}

