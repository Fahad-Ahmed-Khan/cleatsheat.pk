<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toastError, toastSuccess } from '@/admin/swalToast';

const props = defineProps({
    sendRoute: { type: String, required: true },
    templates: { type: Array, default: () => [] },
    recipientLabel: { type: String, default: 'Recipient' },
    recipientPhone: { type: String, default: '' },
    confirmation: { type: Object, default: null },
});

const form = useForm({
    template_key: '',
    message: '',
});

const usesTemplate = computed(() => !!form.template_key);

function submit() {
    form.post(props.sendRoute, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('WhatsApp message sent');
            form.reset('template_key', 'message');
        },
        onError: (errors) => toastError(errors.message || 'Failed to send WhatsApp message'),
    });
}
</script>

<template>
    <div class="card mb-0">
        <div class="card-header py-2">
            <h6 class="card-title m-0">
                <i class="ti tabler-brand-whatsapp text-success me-1"></i>
                WhatsApp
            </h6>
        </div>
        <div class="card-body pt-2 pb-3">
            <div v-if="confirmation" class="mb-3">
                <div class="text-muted small mb-1">COD confirmation</div>
                <span
                    v-if="confirmation.awaiting"
                    class="badge bg-label-warning"
                >Awaiting customer reply</span>
                <span
                    v-else-if="confirmation.confirmed_at"
                    class="badge bg-label-success"
                >Confirmed{{ confirmation.channel ? ` (${confirmation.channel})` : '' }} · {{ confirmation.confirmed_at }}</span>
                <span v-else class="badge bg-label-secondary">Not required</span>
                <div v-if="confirmation.sent_at" class="text-muted small mt-1">Request sent {{ confirmation.sent_at }}</div>
            </div>

            <div class="text-muted small mb-2">{{ recipientLabel }}: <span class="font-monospace">{{ recipientPhone || '—' }}</span></div>

            <div class="mb-2">
                <label class="form-label small">Template (optional)</label>
                <select v-model="form.template_key" class="form-select form-select-sm">
                    <option value="">— Free text —</option>
                    <option v-for="t in templates" :key="t.key" :value="t.key">{{ t.label }}</option>
                </select>
            </div>

            <div v-if="!usesTemplate" class="mb-2">
                <label class="form-label small">Message</label>
                <textarea
                    v-model="form.message"
                    class="form-control form-control-sm"
                    rows="4"
                    placeholder="Type your message…"
                />
            </div>

            <button
                type="button"
                class="btn btn-sm btn-success"
                :disabled="form.processing || (!usesTemplate && !form.message?.trim())"
                @click="submit"
            >
                Send WhatsApp
            </button>
        </div>
    </div>
</template>
