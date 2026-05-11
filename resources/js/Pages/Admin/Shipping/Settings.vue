<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    couriers_for_select: { type: Array, required: true },
    courier_accounts_form: { type: Array, default: () => [] },
});

const form = useForm({
    default_courier_id: props.settings.default_courier_id,
    courier_assignment_default: props.settings.courier_assignment_default,
    auto_book_on_payment_confirmed: props.settings.auto_book_on_payment_confirmed,
    auto_book_cod_orders: props.settings.auto_book_cod_orders,
    tracking_sync_interval_minutes: props.settings.tracking_sync_interval_minutes,
    sender_snapshot: { ...props.settings.sender_snapshot },
    postex_pickup_address_code: props.settings.postex_pickup_address_code ?? '',
    postex_store_address_code: props.settings.postex_store_address_code ?? '',
    default_weight_kg: props.settings.default_weight_kg,
    default_length_cm: props.settings.default_length_cm,
    default_width_cm: props.settings.default_width_cm,
    default_height_cm: props.settings.default_height_cm,
    courier_accounts: props.courier_accounts_form.map((r) => ({ ...r })),
});

/** Which courier account panel is visible (tabs). */
const activeCourierAccountId = ref(props.courier_accounts_form[0]?.id ?? null);

function submit() {
    form.patch(route('admin.shipping-settings.update'));
}
</script>

<template>
    <Head title="Admin — Shipping" />
    <AdminLayout>
        <AdminPageHeader
            title="Shipping & couriers"
            subtitle="Defaults for new orders, parcel dimensions, sender warehouse details, and API accounts per carrier."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Shipping' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Order defaults">
                <div class="row g-3">
                    <div class="col-12">
                        <FormField id="ship_default_courier" label="Default courier" :error="form.errors.default_courier_id">
                            <template #default="{ invalid, describedBy }">
                                <select
                                    id="ship_default_courier"
                                    v-model="form.default_courier_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                >
                                    <option :value="null">None (manual assignment)</option>
                                    <option v-for="c in couriers_for_select" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="ship_assignment" label="Assignment for new orders" :error="form.errors.courier_assignment_default">
                            <template #default="{ invalid, describedBy }">
                                <select
                                    id="ship_assignment"
                                    v-model="form.courier_assignment_default"
                                    class="form-select"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                >
                                    <option value="auto">Automatic (use default courier)</option>
                                    <option value="manual">Manual (admin assigns later)</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="ship_sync" label="Tracking sync interval (minutes)" :error="form.errors.tracking_sync_interval_minutes">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="ship_sync"
                                    v-model.number="form.tracking_sync_interval_minutes"
                                    type="number"
                                    min="5"
                                    max="1440"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                </div>

                <div class="form-check mt-2">
                    <input id="ship_auto_paid" v-model="form.auto_book_on_payment_confirmed" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ship_auto_paid">Auto-book shipment when payment becomes paid</label>
                </div>
                <div class="form-check mt-2">
                    <input id="ship_auto_cod" v-model="form.auto_book_cod_orders" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ship_auto_cod">Auto-book COD orders at checkout</label>
                </div>
            </FormSection>

            <FormSection title="Parcel defaults">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="ship_weight" label="Weight (kg)" :error="form.errors.default_weight_kg">
                            <template #default="{ invalid, describedBy }">
                                <input id="ship_weight" v-model.number="form.default_weight_kg" type="number" step="0.001" min="0.001" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="row g-2">
                            <div class="col-4">
                                <FormField id="ship_len" label="L (cm)" :error="form.errors.default_length_cm">
                                    <template #default="{ invalid, describedBy }">
                                        <input id="ship_len" v-model.number="form.default_length_cm" type="number" step="0.1" min="1" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                            </div>
                            <div class="col-4">
                                <FormField id="ship_w" label="W (cm)" :error="form.errors.default_width_cm">
                                    <template #default="{ invalid, describedBy }">
                                        <input id="ship_w" v-model.number="form.default_width_cm" type="number" step="0.1" min="1" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                            </div>
                            <div class="col-4">
                                <FormField id="ship_h" label="H (cm)" :error="form.errors.default_height_cm">
                                    <template #default="{ invalid, describedBy }">
                                        <input id="ship_h" v-model.number="form.default_height_cm" type="number" step="0.1" min="1" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                            </div>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Sender / warehouse">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="sender_business" label="Business name" :error="form.errors['sender_snapshot.business_name']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_business" v-model="form.sender_snapshot.business_name" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="sender_contact" label="Contact name" :error="form.errors['sender_snapshot.contact_name']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_contact" v-model="form.sender_snapshot.contact_name" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="sender_phone" label="Phone" :error="form.errors['sender_snapshot.phone']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_phone" v-model="form.sender_snapshot.phone" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="sender_email" label="Email" :error="form.errors['sender_snapshot.email']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_email" v-model="form.sender_snapshot.email" type="email" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12">
                        <FormField id="sender_street" label="Street" :error="form.errors['sender_snapshot.line1']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_street" v-model="form.sender_snapshot.line1" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="sender_city" label="City" :error="form.errors['sender_snapshot.city']">
                            <template #default="{ invalid, describedBy }">
                                <input id="sender_city" v-model="form.sender_snapshot.city" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="PostEx defaults" description="Optional: configure pickup/store address codes used when booking PostEx shipments.">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="postex_pickup" label="Pickup address code" :error="form.errors.postex_pickup_address_code">
                            <template #default="{ invalid, describedBy }">
                                <input id="postex_pickup" v-model="form.postex_pickup_address_code" class="form-control" placeholder="e.g. WH-KHI-001" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="postex_store" label="Store address code (optional)" :error="form.errors.postex_store_address_code">
                            <template #default="{ invalid, describedBy }">
                                <input id="postex_store" v-model="form.postex_store_address_code" class="form-control" placeholder="Optional" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection v-if="form.courier_accounts.length" title="Courier API accounts" description="One tab per carrier. Leave token blank to keep the existing secret.">
                <ul class="nav nav-tabs flex-wrap gap-1 mb-0 px-1 pt-1" role="tablist">
                    <li
                        v-for="(row, idx) in form.courier_accounts"
                        :key="row.id"
                        class="nav-item"
                        role="presentation"
                    >
                        <button
                            type="button"
                            class="nav-link py-2 px-3"
                            :class="{ active: activeCourierAccountId === row.id }"
                            role="tab"
                            :aria-selected="activeCourierAccountId === row.id"
                            :aria-controls="`courier-panel-${row.id}`"
                            :id="`courier-tab-${row.id}`"
                            @click="activeCourierAccountId = row.id"
                        >
                            {{ row.courier_name }}
                            <span
                                v-if="form.courier_accounts[idx].is_active"
                                class="badge bg-label-success ms-1"
                            >On</span>
                        </button>
                    </li>
                </ul>

                <div class="border rounded-bottom p-3 bg-body">
                    <template v-for="(row, idx) in form.courier_accounts" :key="row.id">
                        <div
                            v-show="activeCourierAccountId === row.id"
                            :id="`courier-panel-${row.id}`"
                            role="tabpanel"
                            :aria-labelledby="`courier-tab-${row.id}`"
                            tabindex="0"
                        >
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="text-muted small">
                                    Configure credentials for <span class="text-body fw-semibold">{{ row.courier_name }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check mb-0">
                                        <input :id="`ca-cod-${row.id}`" v-model="form.courier_accounts[idx].cod_allowed" type="checkbox" class="form-check-input" />
                                        <label class="form-check-label" :for="`ca-cod-${row.id}`">COD allowed</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input :id="`ca-active-${row.id}`" v-model="form.courier_accounts[idx].is_active" type="checkbox" class="form-check-input" />
                                        <label class="form-check-label" :for="`ca-active-${row.id}`">Active</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input :id="`ca-default-${row.id}`" v-model="form.courier_accounts[idx].is_default" type="checkbox" class="form-check-input" />
                                        <label class="form-check-label" :for="`ca-default-${row.id}`">Default</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <FormField :id="`ca-name-${row.id}`" label="Account label" :error="form.errors[`courier_accounts.${idx}.name`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-name-${row.id}`" v-model="form.courier_accounts[idx].name" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`ca-service-${row.id}`" label="Service code" :error="form.errors[`courier_accounts.${idx}.service_code`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-service-${row.id}`" v-model="form.courier_accounts[idx].service_code" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12">
                                    <FormField :id="`ca-token-${row.id}`" label="API token / primary credential" :error="form.errors[`courier_accounts.${idx}.api_token`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input
                                                :id="`ca-token-${row.id}`"
                                                v-model="form.courier_accounts[idx].api_token"
                                                type="password"
                                                autocomplete="new-password"
                                                placeholder="Leave blank to keep unchanged"
                                                class="form-control"
                                                :class="{ 'is-invalid': invalid }"
                                                :aria-describedby="describedBy"
                                            />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`ca-client-${row.id}`" label="Run Courier — client code" :error="form.errors[`courier_accounts.${idx}.client_code`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-client-${row.id}`" v-model="form.courier_accounts[idx].client_code" class="form-control" placeholder="Leave blank to keep unchanged" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField :id="`ca-profile-${row.id}`" label="Run Courier — profile id" :error="form.errors[`courier_accounts.${idx}.profile_id`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-profile-${row.id}`" v-model="form.courier_accounts[idx].profile_id" class="form-control" placeholder="Leave blank to keep unchanged" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12">
                                    <FormField :id="`ca-vendor-${row.id}`" label="Run Courier — api vendor" :error="form.errors[`courier_accounts.${idx}.api_vendor`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-vendor-${row.id}`" v-model="form.courier_accounts[idx].api_vendor" class="form-control" placeholder="Empty = automatic routing" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12">
                                    <FormField :id="`ca-cities-${row.id}`" label="Allowed cities (comma-separated)" :error="form.errors[`courier_accounts.${idx}.city_restrictions_text`]">
                                        <template #default="{ invalid, describedBy }">
                                            <input :id="`ca-cities-${row.id}`" v-model="form.courier_accounts[idx].city_restrictions_text" class="form-control" placeholder="Empty = all" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                        </template>
                                    </FormField>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save shipping settings</button>
                    <span v-if="form.recentlySuccessful" class="text-muted small">Saved.</span>
                </template>
            </FormSection>

            <div v-else class="mt-3">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">Save shipping settings</button>
                <span v-if="form.recentlySuccessful" class="text-muted small ms-2">Saved.</span>
            </div>
        </form>
    </AdminLayout>
</template>
