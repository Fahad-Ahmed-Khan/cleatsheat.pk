<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    methods: {
        type: Array,
        required: true,
    },
    fallback_online_failed_to_cod: {
        type: Boolean,
        default: true,
    },
});

const form = useForm({
    fallback_online_failed_to_cod: props.fallback_online_failed_to_cod,
    methods: props.methods.map((m) => ({
        id: m.id,
        enabled: !!m.enabled,
        customer_label: m.customer_label,
        fee_fixed: Number(m.fee_fixed),
        fee_percent: Number(m.fee_percent),
        sort_order: m.sort_order,
        gateway_code: m.gateway_code,
    })),
});

/** Which payment method panel is visible (tabs). */
const activeMethodId = ref(props.methods[0]?.id ?? null);

function submit() {
    form.patch(route('admin.payment-settings.update'));
}
</script>

<template>
    <Head title="Admin — Payment settings" />
    <AdminLayout>
        <AdminPageHeader
            title="Payment settings"
            subtitle="Enable gateways, customer-facing labels, transaction fees, and COD fallback when online payments fail."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Payment settings' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Checkout fallback">
                <div class="form-check">
                    <input
                        id="fallbackCod"
                        v-model="form.fallback_online_failed_to_cod"
                        class="form-check-input"
                        type="checkbox"
                    />
                    <label class="form-check-label" for="fallbackCod">
                        Fallback to cash on delivery
                        <div class="text-muted small">
                            If Easypaisa or JazzCash fails, automatically convert the order to COD when possible.
                        </div>
                    </label>
                </div>
            </FormSection>

            <FormSection
                v-if="form.methods.length"
                title="Payment methods"
                description="One tab per gateway. All methods are saved together when you click Save."
            >
                <ul class="nav nav-tabs flex-wrap gap-1 mb-0 px-1 pt-1" role="tablist">
                    <li
                        v-for="(row, idx) in form.methods"
                        :key="row.id"
                        class="nav-item"
                        role="presentation"
                    >
                        <button
                            type="button"
                            class="nav-link py-2 px-3 d-flex align-items-center gap-2 text-start"
                            :class="{ active: activeMethodId === row.id }"
                            role="tab"
                            :aria-selected="activeMethodId === row.id"
                            :aria-controls="`payment-panel-${row.id}`"
                            :id="`payment-tab-${row.id}`"
                            @click="activeMethodId = row.id"
                        >
                            <span class="d-flex flex-column align-items-start">
                                <span class="fw-medium">{{ row.customer_label || row.gateway_code }}</span>
                                <code class="small text-muted fw-normal">{{ row.gateway_code }}</code>
                            </span>
                            <span
                                v-if="form.methods[idx].enabled"
                                class="badge bg-label-success flex-shrink-0"
                            >On</span>
                        </button>
                    </li>
                </ul>

                <div class="border rounded-bottom p-3 bg-body">
                    <template v-for="(row, idx) in form.methods" :key="row.id">
                        <div
                            v-show="activeMethodId === row.id"
                            :id="`payment-panel-${row.id}`"
                            role="tabpanel"
                            :aria-labelledby="`payment-tab-${row.id}`"
                            tabindex="0"
                        >
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="text-muted small">
                                    Gateway <code>{{ row.gateway_code }}</code>
                                </div>
                                <div class="form-check mb-0">
                                    <input
                                        :id="`enabled-${row.id}`"
                                        v-model="form.methods[idx].enabled"
                                        class="form-check-input"
                                        type="checkbox"
                                    />
                                    <label class="form-check-label" :for="`enabled-${row.id}`">Enabled at checkout</label>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <FormField :id="`label-${row.id}`" label="Label shown to customers" :error="form.errors[`methods.${idx}.customer_label`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input
                                                :id="`label-${row.id}`"
                                                v-model="form.methods[idx].customer_label"
                                                type="text"
                                                required
                                                class="form-control"
                                                :class="{ 'is-invalid': invalid }"
                                                :aria-describedby="describedBy"
                                            />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`sort-${row.id}`" label="Sort order" :error="form.errors[`methods.${idx}.sort_order`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input
                                                :id="`sort-${row.id}`"
                                                v-model.number="form.methods[idx].sort_order"
                                                type="number"
                                                min="0"
                                                required
                                                class="form-control"
                                                :class="{ 'is-invalid': invalid }"
                                                :aria-describedby="describedBy"
                                            />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`feeFixed-${row.id}`" label="Fixed fee (PKR)" :error="form.errors[`methods.${idx}.fee_fixed`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input
                                                :id="`feeFixed-${row.id}`"
                                                v-model.number="form.methods[idx].fee_fixed"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                required
                                                class="form-control"
                                                :class="{ 'is-invalid': invalid }"
                                                :aria-describedby="describedBy"
                                            />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`feePct-${row.id}`" label="Percent fee (%)" :error="form.errors[`methods.${idx}.fee_percent`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input
                                                :id="`feePct-${row.id}`"
                                                v-model.number="form.methods[idx].fee_percent"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                max="100"
                                                required
                                                class="form-control"
                                                :class="{ 'is-invalid': invalid }"
                                                :aria-describedby="describedBy"
                                            />
                                        </template>
                                    </FormField>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </FormSection>

            <div v-else class="alert alert-secondary mb-0" role="status">
                No payment methods are configured in the database. You can still save checkout fallback.
            </div>

            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    Save settings
                </button>
                <span v-if="form.recentlySuccessful" class="text-muted small">Saved.</span>
            </div>
        </form>
    </AdminLayout>
</template>
