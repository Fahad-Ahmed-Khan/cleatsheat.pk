import Swal from 'sweetalert2';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

function normalizeErrorMessage(err) {
    if (!err) return null;
    if (typeof err === 'string') return err;
    if (Array.isArray(err)) return err.filter(Boolean).join('\n') || null;
    if (typeof err === 'object') {
        // Inertia validation errors: { field: ["msg"] }
        const first = Object.values(err).flat().find(Boolean);
        if (first) return String(first);
    }
    return null;
}

export function toastSuccess(message = 'Saved') {
    toast.fire({ icon: 'success', title: message });
}

export function toastError(message = 'Something went wrong') {
    toast.fire({ icon: 'error', title: message });
}

export function toastFromInertiaError(errors, fallback = 'Please fix the errors and try again') {
    toastError(normalizeErrorMessage(errors) ?? fallback);
}

export async function confirmDanger({ title = 'Are you sure?', text = '', confirmText = 'Yes, continue' } = {}) {
    const res = await Swal.fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ff4c51',
    });
    return !!res.isConfirmed;
}

