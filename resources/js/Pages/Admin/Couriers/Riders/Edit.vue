<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import { computed } from 'vue';
import WhatsAppSendPanel from '@/Components/Admin/WhatsAppSendPanel.vue';

const props = defineProps({
    rider: { type: Object, required: true },
    couriers: { type: Array, default: () => [] },
    whatsapp_templates: { type: Array, default: () => [] },
    whatsapp_send_route: { type: String, default: null },
});

const isEdit = computed(() => !!props.rider.id);

const form = useForm({
    courier_id: props.rider.courier_id ?? '',
    name: props.rider.name ?? '',
    phone: props.rider.phone ?? '',
    alt_phone: props.rider.alt_phone ?? '',
    is_active: props.rider.is_active !== false,
    is_primary: !!props.rider.is_primary,
    notes: props.rider.notes ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(route('admin.riders.update', props.rider.id));
    } else {
        form.post(route('admin.riders.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Admin — Edit rider' : 'Admin — Add rider'" />
    <AdminLayout>
        <AdminPageHeader
            :title="isEdit ? 'Edit rider' : 'Add rider'"
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Riders', href: route('admin.riders.index') },
                { label: isEdit ? rider.name : 'New' },
            ]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Rider details">
                <div class="row g-3">
                    <div class="col-md-6">
                        <FormField id="courier_id" label="Courier company" :error="form.errors.courier_id">
                            <template #default="{ invalid, describedBy }">
                                <select id="courier_id" v-model="form.courier_id" class="form-select" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                    <option value="">Select courier…</option>
                                    <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="name" label="Full name" :error="form.errors.name">
                            <template #default="{ invalid, describedBy }">
                                <input id="name" v-model="form.name" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="phone" label="WhatsApp number" hint="E.164 or local PK, e.g. +923001234567." :error="form.errors.phone">
                            <template #default="{ invalid, describedBy }">
                                <input id="phone" v-model="form.phone" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="alt_phone" label="Alternate phone (optional)" :error="form.errors.alt_phone">
                            <template #default="{ invalid, describedBy }">
                                <input id="alt_phone" v-model="form.alt_phone" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="active" v-model="form.is_active" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="primary" v-model="form.is_primary" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="primary">Primary rider for this courier</label>
                        </div>
                        <div class="text-muted small">Only one primary rider per courier. The primary rider receives the daily pickup notice.</div>
                    </div>
                </div>

                <FormField id="notes" label="Notes (admin-only)" :error="form.errors.notes">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="notes" v-model="form.notes" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" rows="3" />
                    </template>
                </FormField>

                <template #actions>
                    <Link :href="route('admin.riders.index')" class="btn btn-outline-secondary">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Add rider' }}
                    </button>
                </template>
            </FormSection>
        </form>

        <div v-if="whatsapp_send_route" class="row mt-3">
            <div class="col-lg-6">
                <WhatsAppSendPanel
                    :send-route="whatsapp_send_route"
                    :templates="whatsapp_templates"
                    recipient-label="Rider phone"
                    :recipient-phone="rider.phone"
                />
            </div>
        </div>
    </AdminLayout>
</template>
