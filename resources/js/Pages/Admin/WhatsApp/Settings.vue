<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    enabled_customer_notifications: !!props.settings.enabled_customer_notifications,
    enabled_admin_notifications: !!props.settings.enabled_admin_notifications,
    admin_recipients_text: props.settings.admin_recipients_text ?? '',
});

function submit() {
    form.patch(route('admin.whatsapp-settings.update'));
}
</script>

<template>
    <Head title="Admin — WhatsApp" />
    <AdminLayout>
        <AdminPageHeader
            title="WhatsApp notifications"
            subtitle="Control customer and admin alerts."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'WhatsApp' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Toggles">
                <div class="form-check mb-3">
                    <input
                        id="waCustomer"
                        v-model="form.enabled_customer_notifications"
                        class="form-check-input"
                        type="checkbox"
                    />
                    <label class="form-check-label" for="waCustomer">
                        Customer notifications
                        <div class="text-muted small">
                            Order placed, payment received, and order status updates.
                        </div>
                    </label>
                </div>

                <div class="form-check">
                    <input
                        id="waAdmin"
                        v-model="form.enabled_admin_notifications"
                        class="form-check-input"
                        type="checkbox"
                    />
                    <label class="form-check-label" for="waAdmin">
                        Admin new-order alerts
                        <div class="text-muted small">
                            One WhatsApp message per line below when a new order is created.
                        </div>
                    </label>
                </div>
            </FormSection>

            <FormSection title="Admin recipient numbers">
                <FormField
                    id="waRecipients"
                    label="Numbers (one per line)"
                    :error="form.errors.admin_recipients || form.errors['admin_recipients.0']"
                    hint="E.164 or local PK format (e.g. +923001234567 or 03001234567)."
                >
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="waRecipients"
                            v-model="form.admin_recipients_text"
                            rows="6"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                            placeholder="+923001234567&#10;+923007654321"
                        />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        Save
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-muted small">Saved.</span>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
