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
    header_text: props.template.header_text ?? '',
    footer_text: props.template.footer_text ?? '',
    url_buttons: Array.isArray(props.template.url_buttons) && props.template.url_buttons.length
        ? props.template.url_buttons.map((b) => ({ text: b.text, url: b.url }))
        : [],
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

function addUrlButton() {
    if (form.url_buttons.length >= 2) return;
    form.url_buttons.push({ text: '', url: '' });
}

function removeUrlButton(idx) {
    form.url_buttons.splice(idx, 1);
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
                description="Use placeholders like {name}, {order}, {total}, {city}, {phone}, {courier}, {tracking_number}, {tracking_url}, {review_url}. They are replaced at send time."
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
                title="Header, footer & link buttons"
                description="Header and footer brand the message (max 60 characters each, header must be static text for Meta approval). Link buttons open a URL — use {order_number} in the URL for an order-specific link, e.g. the Track Order page."
            >
                <div class="row g-3">
                    <div class="col-md-6">
                        <FormField id="header_text" label="Header (optional)" :error="form.errors.header_text" hint="Shown in bold above the body. No placeholders.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="header_text"
                                    v-model="form.header_text"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                    maxlength="60"
                                    placeholder="e.g. Order Confirmed ✓"
                                />
                                <div class="form-text text-end">{{ (form.header_text || '').length }}/60</div>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="footer_text" label="Footer (optional)" :error="form.errors.footer_text" hint="Small muted text under the message.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="footer_text"
                                    v-model="form.footer_text"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                    maxlength="60"
                                    placeholder="e.g. CleatSheat.pk - thank you for shopping with us"
                                />
                                <div class="form-text text-end">{{ (form.footer_text || '').length }}/60</div>
                            </template>
                        </FormField>
                    </div>
                </div>

                <div v-for="(btn, idx) in form.url_buttons" :key="idx" class="row g-2 mb-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Button label (max 25)</label>
                        <input
                            v-model="btn.text"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors[`url_buttons.${idx}.text`] }"
                            maxlength="25"
                            placeholder="Track Order"
                        />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL</label>
                        <input
                            v-model="btn.url"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': form.errors[`url_buttons.${idx}.url`] }"
                            placeholder="https://example.com/track-order?order={order_number}"
                        />
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100" @click="removeUrlButton(idx)">Remove</button>
                    </div>
                </div>
                <button
                    v-if="form.url_buttons.length < 2"
                    type="button"
                    class="btn btn-outline-primary"
                    @click="addUrlButton"
                >
                    Add link button
                </button>
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

                <div
                    v-if="isEdit && template.meta_sync_status"
                    class="alert small py-2"
                    :class="template.meta_sync_error ? 'alert-warning' : 'alert-secondary'"
                >
                    <strong>Meta sync:</strong> {{ template.meta_sync_status }}
                    <span v-if="template.meta_last_synced_at"> · {{ template.meta_last_synced_at }}</span>
                    <span v-if="template.meta_sync_error" class="d-block mt-1">{{ template.meta_sync_error }}</span>
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
                    <button
                        v-if="isEdit && cloud_enabled && !form.has_buttons"
                        type="button"
                        class="btn btn-outline-warning"
                        :disabled="syncing"
                        title="Delete and recreate approved template when local copy differs"
                        @click="syncToMeta(true)"
                    >
                        {{ syncing ? 'Syncing…' : 'Force re-sync' }}
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create template' }}
                    </button>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
