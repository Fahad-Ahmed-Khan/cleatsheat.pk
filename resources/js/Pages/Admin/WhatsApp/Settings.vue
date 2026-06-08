<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import { ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    webhook_url: { type: String, default: '' },
    cloud_enabled: { type: Boolean, default: false },
});

const form = useForm({
    enabled_customer_notifications: !!props.settings.enabled_customer_notifications,
    enabled_admin_notifications: !!props.settings.enabled_admin_notifications,
    enabled_cod_confirmation: !!props.settings.enabled_cod_confirmation,
    enabled_shipment_status_customer_alerts: !!props.settings.enabled_shipment_status_customer_alerts,
    enabled_pickup_notices: !!props.settings.enabled_pickup_notices,
    pickup_notice_time: props.settings.pickup_notice_time ?? '11:00',
    cloud_webhook_verify_token: props.settings.cloud_webhook_verify_token ?? '',
    marketing_opt_out_keyword: props.settings.marketing_opt_out_keyword ?? 'STOP',
    promotional_throttle_per_minute: props.settings.promotional_throttle_per_minute ?? 20,
    admin_recipients_text: props.settings.admin_recipients_text ?? '',
});

const copiedField = ref(null);

function submit() {
    form.patch(route('admin.whatsapp-settings.update'));
}

function copy(value, field) {
    if (!value) return;
    navigator.clipboard?.writeText(value).then(() => {
        copiedField.value = field;
        setTimeout(() => { copiedField.value = null; }, 1500);
    });
}
</script>

<template>
    <Head title="Admin — WhatsApp settings" />
    <AdminLayout>
        <AdminPageHeader
            title="WhatsApp notifications"
            subtitle="Toggles and integration settings for automated and manual messaging."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'WhatsApp' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Channel toggles" description="Master switches for outbound traffic. Disabling a category stops both automatic and manual sends for that audience.">
                <div class="form-check form-switch mb-3">
                    <input id="waCustomer" v-model="form.enabled_customer_notifications" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="waCustomer">
                        Customer notifications
                        <div class="text-muted small">Order placed, payment received, status updates, shipment events.</div>
                    </label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input id="waAdmin" v-model="form.enabled_admin_notifications" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="waAdmin">
                        Admin new-order alerts
                        <div class="text-muted small">Sent to the numbers below on every new order.</div>
                    </label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input id="waCodConfirm" v-model="form.enabled_cod_confirmation" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="waCodConfirm">
                        COD order confirmation
                        <div class="text-muted small">Sends interactive Confirm / Cancel buttons to the customer for COD orders. Requires WhatsApp Cloud API.</div>
                    </label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input id="waShipmentAlerts" v-model="form.enabled_shipment_status_customer_alerts" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="waShipmentAlerts">
                        Shipment-driven customer alerts
                        <div class="text-muted small">When a courier webhook updates shipment status (in transit, out for delivery, delivered, returned), notify the customer.</div>
                    </label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input id="waPickup" v-model="form.enabled_pickup_notices" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="waPickup">
                        Daily rider pickup notices
                        <div class="text-muted small">Sends the primary rider per courier company a daily message with the parcel count and tracking numbers.</div>
                    </label>
                </div>
            </FormSection>

            <FormSection title="Scheduling">
                <FormField id="pickupTime" label="Daily pickup notice time (24h)" hint="Time when the system sends the daily pickup notice to each courier's primary rider. Format HH:MM, e.g. 11:00." :error="form.errors.pickup_notice_time">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="pickupTime"
                            v-model="form.pickup_notice_time"
                            type="time"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Cloud API webhook" description="Configure these values inside Meta App Dashboard → WhatsApp → Configuration. The verify token must match the one stored here.">
                <FormField id="webhookUrl" label="Webhook URL (paste in Meta)" hint="Read-only. Cloud API will POST inbound messages and delivery receipts here.">
                    <template #default>
                        <div class="input-group">
                            <input id="webhookUrl" :value="webhook_url" class="form-control font-monospace" readonly />
                            <button class="btn btn-outline-primary" type="button" @click="copy(webhook_url, 'url')">
                                {{ copiedField === 'url' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </template>
                </FormField>

                <FormField id="verifyToken" label="Verify token" hint="Used during the Meta webhook handshake. Leave blank to auto-generate; otherwise paste your own." :error="form.errors.cloud_webhook_verify_token">
                    <template #default="{ invalid, describedBy }">
                        <div class="input-group">
                            <input
                                id="verifyToken"
                                v-model="form.cloud_webhook_verify_token"
                                class="form-control font-monospace"
                                :class="{ 'is-invalid': invalid }"
                                :aria-describedby="describedBy"
                                placeholder="(auto-generated on first save)"
                            />
                            <button class="btn btn-outline-primary" type="button" :disabled="!form.cloud_webhook_verify_token" @click="copy(form.cloud_webhook_verify_token, 'token')">
                                {{ copiedField === 'token' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </template>
                </FormField>

                <div v-if="!cloud_enabled" class="alert alert-warning small mt-2">
                    Cloud API is currently disabled. Set <code>WHATSAPP_CLOUD_ENABLED=true</code> in <code>.env</code> and provide
                    <code>WHATSAPP_CLOUD_TOKEN</code>, <code>WHATSAPP_CLOUD_PHONE_NUMBER_ID</code>, <code>WHATSAPP_CLOUD_WABA_ID</code> (for template sync),
                    and <code>WHATSAPP_CLOUD_APP_SECRET</code> to enable interactive buttons and webhook signature verification.
                </div>
            </FormSection>

            <FormSection title="Marketing">
                <div class="row g-3">
                    <div class="col-md-6">
                        <FormField id="optOutKeyword" label="Opt-out keyword" hint="Customers replying with this exact word are flagged as opted-out and excluded from promotional broadcasts." :error="form.errors.marketing_opt_out_keyword">
                            <template #default="{ invalid, describedBy }">
                                <input id="optOutKeyword" v-model="form.marketing_opt_out_keyword" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="throttle" label="Throttle (messages / minute)" hint="Soft cap to avoid being flagged as spam. 20 = roughly one message every 3 seconds." :error="form.errors.promotional_throttle_per_minute">
                            <template #default="{ invalid, describedBy }">
                                <input id="throttle" v-model.number="form.promotional_throttle_per_minute" type="number" min="1" max="600" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
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
                        Save settings
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-muted small">Saved.</span>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
