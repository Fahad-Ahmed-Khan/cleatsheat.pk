<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const form = useForm({
    code: '',
    type: 'percent',
    value: '',
    min_cart_total: '',
    starts_at: '',
    ends_at: '',
    max_redemptions: '',
    is_active: true,
});

const valueHint = computed(() =>
    form.type === 'percent'
        ? 'Percentage off subtotal (0–100).'
        : 'Fixed amount off subtotal (PKR).',
);

function submit() {
    form.post(route('admin.coupons.store'));
}
</script>

<template>
    <Head title="Admin — New coupon" />
    <AdminLayout>
        <AdminPageHeader
            title="New coupon"
            subtitle="Create a discount code for checkout."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Coupons', href: route('admin.coupons.index') },
                { label: 'New' },
            ]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Discount">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_code" label="Code" :error="form.errors.code" hint="Letters and numbers; stored uppercase.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_code"
                                    v-model="form.code"
                                    type="text"
                                    class="form-control font-monospace text-uppercase"
                                    autocomplete="off"
                                    maxlength="255"
                                    placeholder="e.g. SUMMER20"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_type" label="Type" :error="form.errors.type">
                            <template #default="{ invalid, describedBy }">
                                <select
                                    id="coupon_type"
                                    v-model="form.type"
                                    class="form-select"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                >
                                    <option value="percent">Percent off</option>
                                    <option value="fixed">Fixed amount (PKR)</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_value" label="Value" :error="form.errors.value" :hint="valueHint">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_value"
                                    v-model="form.value"
                                    type="number"
                                    min="0"
                                    :max="form.type === 'percent' ? 100 : undefined"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6 d-flex align-items-end">
                        <div class="form-check mb-0">
                            <input id="coupon_active" v-model="form.is_active" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="coupon_active">Active</label>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection
                title="Optional rules"
                description="Leave blank for no restriction on that rule."
            >
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_min_cart" label="Minimum cart subtotal (PKR)" :error="form.errors.min_cart_total">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_min_cart"
                                    v-model="form.min_cart_total"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    placeholder="No minimum"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_max_redemptions" label="Max redemptions" :error="form.errors.max_redemptions" hint="Total uses across all customers.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_max_redemptions"
                                    v-model="form.max_redemptions"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="form-control"
                                    placeholder="Unlimited"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_starts" label="Starts at" :error="form.errors.starts_at">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_starts"
                                    v-model="form.starts_at"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="coupon_ends" label="Ends at" :error="form.errors.ends_at">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="coupon_ends"
                                    v-model="form.ends_at"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                </div>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create coupon</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.coupons.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
