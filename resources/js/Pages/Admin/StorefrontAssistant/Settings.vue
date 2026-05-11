<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import { computed } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    enabled: !!props.settings.enabled,
    preview_enabled: !!props.settings.preview_enabled,
    delay_seconds: props.settings.delay_seconds ?? 4,
    snooze_days: props.settings.snooze_days ?? 7,
    allowed_routes_text: props.settings.allowed_routes_text ?? '',
    ui: props.settings.ui ?? {},
    steps: Array.isArray(props.settings.steps) ? props.settings.steps : [],
    mapping: props.settings.mapping ?? {},
});

const stepsJson = computed({
    get: () => JSON.stringify(form.steps ?? [], null, 2),
    set: (v) => {
        try {
            const parsed = JSON.parse(String(v || '[]'));
            form.steps = Array.isArray(parsed) ? parsed : [];
        } catch {
            // keep existing until JSON is valid
        }
    },
});

const mappingJson = computed({
    get: () => JSON.stringify(form.mapping ?? {}, null, 2),
    set: (v) => {
        try {
            const parsed = JSON.parse(String(v || '{}'));
            form.mapping = parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            // keep existing until JSON is valid
        }
    },
});

function submit() {
    form.patch(route('admin.storefront-assistant.update'));
}
</script>

<template>
    <Head title="Admin — Storefront Assistant" />
    <AdminLayout>
        <AdminPageHeader
            title="Storefront Assistant"
            subtitle="A guided mini flow that redirects to filtered Shop results."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Storefront Assistant' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Toggles">
                <div class="form-check mb-3">
                    <input id="saEnabled" v-model="form.enabled" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="saEnabled">
                        Enabled
                        <div class="text-muted small">
                            When disabled, the assistant never shows on the storefront.
                        </div>
                    </label>
                </div>

                <div class="form-check">
                    <input id="saPreview" v-model="form.preview_enabled" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="saPreview">
                        Product preview (experimental)
                        <div class="text-muted small">
                            When enabled, the assistant may preview matching products before redirecting.
                        </div>
                    </label>
                </div>
            </FormSection>

            <FormSection title="Timing">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="saDelay" label="Delay seconds" :error="form.errors.delay_seconds" hint="Seconds to wait before showing the widget on eligible pages.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="saDelay"
                                    v-model.number="form.delay_seconds"
                                    type="number"
                                    min="0"
                                    max="30"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saSnooze" label="Snooze days" :error="form.errors.snooze_days" hint="If the user closes it, hide it for this many days.">
                            <template #default="{ invalid, describedBy }">
                                <input
                                    id="saSnooze"
                                    v-model.number="form.snooze_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    class="form-control"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Pages allowlist">
                <FormField
                    id="saAllowlist"
                    label="Eligible route names (one per line)"
                    :error="form.errors.allowed_routes || form.errors['allowed_routes.0']"
                    hint="Example: store.home or store.shop. Leave empty to show nowhere."
                >
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="saAllowlist"
                            v-model="form.allowed_routes_text"
                            rows="6"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                            placeholder="store.home&#10;store.shop"
                        />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Copy">
                <FormField id="saTitle" label="Title" :error="form.errors['ui.title']">
                    <template #default="{ invalid, describedBy }">
                        <input id="saTitle" v-model="form.ui.title" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="saSubtitle" label="Subtitle" :error="form.errors['ui.subtitle']">
                    <template #default="{ invalid, describedBy }">
                        <input id="saSubtitle" v-model="form.ui.subtitle" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="saWelcome" label="Welcome message" :error="form.errors['ui.welcome']">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="saWelcome" v-model="form.ui.welcome" rows="3" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="saOpenLabel" label="Widget label" :error="form.errors['ui.open_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saOpenLabel" v-model="form.ui.open_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saStartLabel" label="Start button" :error="form.errors['ui.start_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saStartLabel" v-model="form.ui.start_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saNextLabel" label="Next button" :error="form.errors['ui.next_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saNextLabel" v-model="form.ui.next_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saBackLabel" label="Back button" :error="form.errors['ui.back_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saBackLabel" v-model="form.ui.back_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saSubmitLabel" label="Submit button" :error="form.errors['ui.submit_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saSubmitLabel" v-model="form.ui.submit_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="saCloseLabel" label="Close button" :error="form.errors['ui.close_button_label']">
                            <template #default="{ invalid, describedBy }">
                                <input id="saCloseLabel" v-model="form.ui.close_button_label" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Steps (JSON)">
                <FormField
                    id="saStepsJson"
                    label="Steps JSON"
                    :error="form.errors.steps || form.errors['steps.0.key'] || form.errors['steps.0.label']"
                    hint="Advanced: edit the ordered steps list. Keep keys stable (used for mapping)."
                >
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="saStepsJson"
                            v-model="stepsJson"
                            rows="12"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Mapping (JSON)">
                <FormField
                    id="saMappingJson"
                    label="Step key → query param"
                    :error="form.errors.mapping"
                    hint='Example: { "size_uk": "size_uk", "type": "type" }'
                >
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="saMappingJson"
                            v-model="mappingJson"
                            rows="6"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
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

