import { onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toastError, toastSuccess } from './swalToast';

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
        if (flash.success) toastSuccess(flash.success);
        if (flash.error) toastError(flash.error);
        if (flash.status) toastSuccess(flash.status);

        // Existing app already shares this key (used for checkout); keep compatible.
        if (page.props.flashPaymentError) toastError(page.props.flashPaymentError);
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

