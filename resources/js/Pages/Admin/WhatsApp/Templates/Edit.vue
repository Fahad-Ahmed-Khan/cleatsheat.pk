<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    template: { type: Object, required: true },
    audiences: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    cloud_enabled: { type: Boolean, default: false },
});

const syncing = ref(false);

const isEdit = computed(() => !!props.template.id);

const form = useForm({
    key: props.template.key ?? '',
    label: props.template.label ?? '',
    audience: props.template.audience ?? 'customer',
    category: props.template.category ?? 'transactional',
    body: props.template.body ?? '',
    cloud_template_name: props.template.cloud_template_name ?? '',
    cloud_template_language: props.template.cloud_template_language ?? 'en_US',
    has_buttons: !!props.template.has_buttons,
    button_payloads: Array.isArray(props.template.button_payloads) && props.template.button_payloads.length
        ? props.template.button_payloads.map((b) => ({ id: b.id, title: b.title }))
        : [],
    is_active: props.template.is_active !== false,
    description: props.template.description ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(route('admin.whatsapp-templates.update', props.template.id));
    } else {
        form.post(route('admin.whatsapp-templates.store'));
    }
}

function addButton() {
    if (form.button_payloads.length >= 3) return;
    form.button_payloads.push({ id: '', title: '' });
}

function removeButton(idx) {
    form.button_payloads.splice(idx, 1);
}

function syncToMeta(force = false) {
    if (!isEdit.value || !props.cloud_enabled || form.has_buttons) return;
    const msg = force
        ? 'Force re-sync? Approved Meta templates will be deleted and recreated.'
        : 'Push this template to Meta? New submissions require Meta approval.';
    if (!confirm(msg)) return;
    syncing.value = true;
    router.post(route('admin.whatsapp-templates.sync-meta', props.template.id), { force }, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
}
</script>

<template>
    <Head :title="isEdit ? 'Admin — Edit WhatsApp template' : 'Admin — New WhatsApp template'" />
    <AdminLayout>
        <AdminPageHeader
            :title="isEdit ? 'Edit WhatsApp template' : 'New WhatsApp template'"
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'WhatsApp templates', href: route('admin.whatsapp-templates.index') },
                { label: isEdit ? template.label : 'New' },
            ]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Identity">
                <div class="row g-3">
                    <div class="col-md-6">
                        <FormField id="key" label="Key" :error="form.errors.key" hint="Lowercase letters, numbers, underscores. Used in code. System templates cannot be renamed.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="key"
                                    v-model="form.key"
                                    class="form-control font-monospace"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                    :disabled="template.is_system"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="label" label="Display label" :error="form.errors.label">
                            <template #default="{ invalid, describedBy }">
                                <input id="label" v-model="form.label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="audience" label="Audience" :error="form.errors.audience">
                            <template #default="{ invalid, describedBy }">
                                <select id="audience" v-model="form.audience" class="form-select" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                    <option v-for="a in audiences" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="category" label="Category" :error="form.errors.category">
                            <template #default="{ invalid, describedBy }">
                                <select id="category" v-model="form.category" class="form-select" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                    <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-3">
                            <input id="active" v-model="form.is_active" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection
                title="Message body"
                description="Use placeholders like {name}, {order}, {total}, {city}, {phone}. They are replaced at send time."
            >
                <FormField id="body" label="Body" :error="form.errors.body">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="body"
                            v-model="form.body"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                            rows="8"
                        />
                    </template>
                </FormField>

                <FormField id="description" label="Internal description (admin-only)" :error="form.errors.description">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="description" v-model="form.description" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" rows="2" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection
                title="Interactive buttons (Cloud API only)"
                description="When enabled, the customer sees tappable buttons. Use {order_id} in the payload to bind to the order."
            >
                <div class="form-check form-switch mb-3">
                    <input id="hasButtons" v-model="form.has_buttons" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="hasButtons">Use interactive buttons</label>
                </div>

                <div v-if="form.has_buttons">
                    <div v-for="(btn, idx) in form.button_payloads" :key="idx" class="row g-2 mb-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Payload ID</label>
                            <input
                                v-model="btn.id"
                                class="form-control font-monospace"
                                :class="{ 'is-invalid': form.errors[`button_payloads.${idx}.id`] }"
                                placeholder="order:{order_id}:confirm"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Button title (max 20)</label>
                            <input
                                v-model="btn.title"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`button_payloads.${idx}.title`] }"
                                maxlength="20"
                            />
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger w-100" @click="removeButton(idx)">Remove</button>
                        </div>
                    </div>
                    <button
                        v-if="form.button_payloads.length < 3"
                        type="button"
                        class="btn btn-outline-primary"
                        @click="addButton"
                    >
                        Add button
                    </button>
                </div>
            </FormSection>

            <FormSection
                title="WhatsApp Cloud API template"
                description="Set a Meta template name (or leave blank to use the template key). After saving body changes, click Sync to Meta or run php artisan whatsapp:sync-templates. Placeholders become Meta variables {{1}}, {{2}}, … in order of appearance."
            >
                <div class="row g-3">
                    <div class="col-md-8">
                        <FormField id="cloud_template_name" label="Cloud template name" :error="form.errors.cloud_template_name">
                            <template #default="{ invalid, describedBy }">
                                <input id="cloud_template_name" v-model="form.cloud_template_name" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="cloud_template_language" label="Language" :error="form.errors.cloud_template_language">
                            <template #default="{ invalid, describedBy }">
                                <input id="cloud_template_language" v-model="form.cloud_template_language" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                </div>

                <div v-if="isEdit && template.meta_sync_status" class="alert alert-secondary small py-2">
                    <strong>Meta sync:</strong> {{ template.meta_sync_status }}
                    <span v-if="template.meta_last_synced_at"> · {{ template.meta_last_synced_at }}</span>
                    <span v-if="template.meta_sync_error" class="text-danger d-block">{{ template.meta_sync_error }}</span>
                </div>

                <template #actions>
                    <Link :href="route('admin.whatsapp-templates.index')" class="btn btn-outline-secondary">Cancel</Link>
                    <button
                        v-if="isEdit && cloud_enabled && !form.has_buttons"
                        type="button"
                        class="btn btn-outline-primary"
                        :disabled="syncing"
                        @click="syncToMeta(false)"
                    >
                        {{ syncing ? 'Syncing…' : 'Sync to Meta' }}
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create template' }}
                    </button>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
