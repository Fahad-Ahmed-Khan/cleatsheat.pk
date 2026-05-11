<script setup>
const props = defineProps({
    id: { type: String, default: 'confirmDialog' },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: 'Are you sure?' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    confirmVariant: { type: String, default: 'danger' },
});

const emit = defineEmits(['confirm']);

function onConfirm() {
    emit('confirm');
}
</script>

<template>
    <div class="modal fade" tabindex="-1" :id="id" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ message }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ cancelText }}
                    </button>
                    <button type="button" class="btn" :class="`btn-${confirmVariant}`" data-bs-dismiss="modal" @click="onConfirm">
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

