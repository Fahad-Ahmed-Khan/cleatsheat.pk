<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    campaign: { type: Object, required: true },
    templates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const isEdit = !!props.campaign.id;
const audiencePreview = ref(null);

const form = useForm({
    name: props.campaign.name ?? '',
    template_id: props.campaign.template_id ?? '',
    scheduled_for: props.campaign.scheduled_for ?? '',
    segment: {
        opt_in_only: props.campaign.segment?.opt_in_only !== false,
        ordered_within_days: props.campaign.segment?.ordered_within_days ?? '',
        city: props.campaign.segment?.city ?? '',
        category_id: props.campaign.segment?.category_id ?? '',
        phones: props.campaign.segment?.phones ?? '',
    },
});

async function previewAudience() {
    const { data } = await axios.post(route('admin.whatsapp-campaigns.preview-count'), {
        segment: form.segment,
    });
    audiencePreview.value = data.count;
}

function submit() {
    if (isEdit) {
        form.put(route('admin.whatsapp-campaigns.update', props.campaign.id));
    } else {
        form.post(route('admin.whatsapp-campaigns.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit campaign' : 'New campaign'" />
    <AdminLayout>
        <AdminPageHeader
            :title="isEdit ? 'Edit campaign' : 'New campaign'"
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Campaigns', href: route('admin.whatsapp-campaigns.index') },
                { label: isEdit ? 'Edit' : 'New' },
            ]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Campaign">
                <FormField id="name" label="Name" :error="form.errors.name">
                    <template #default="{ invalid, describedBy }">
                        <input id="name" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="template_id" label="Template" :error="form.errors.template_id">
                    <template #default="{ invalid, describedBy }">
                        <select id="template_id" v-model="form.template_id" class="form-select" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                            <option value="">Default promotional</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.label }} ({{ t.key }})</option>
                        </select>
                    </template>
                </FormField>

                <FormField id="scheduled_for" label="Schedule for (optional)" hint="Leave empty to save as draft and send manually from the campaign page.">
                    <template #default="{ invalid, describedBy }">
                        <input id="scheduled_for" v-model="form.scheduled_for" type="datetime-local" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Audience" description="Recipients must have a phone on file. Marketing sends skip opted-out users when opt-in only is checked.">
                <div class="form-check mb-3">
                    <input id="optIn" v-model="form.segment.opt_in_only" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optIn">Only customers who have not texted STOP</label>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <FormField id="days" label="Ordered in last N days">
                            <input id="days" v-model.number="form.segment.ordered_within_days" type="number" min="1" class="form-control" placeholder="e.g. 30" />
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="city" label="City contains">
                            <input id="city" v-model="form.segment.city" type="text" class="form-control" />
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="category" label="Bought from category">
                            <select id="category" v-model="form.segment.category_id" class="form-select">
                                <option value="">Any</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </FormField>
                    </div>
                </div>

                <FormField id="phones" label="Extra phone numbers" hint="Comma or newline separated. Added on top of segment filters.">
                    <textarea id="phones" v-model="form.segment.phones" class="form-control font-monospace" rows="3" />
                </FormField>

                <button type="button" class="btn btn-outline-secondary btn-sm" @click="previewAudience">
                    Preview audience size
                </button>
                <span v-if="audiencePreview !== null" class="ms-2 small text-muted">≈ {{ audiencePreview }} recipients</span>

                <template #actions>
                    <Link :href="route('admin.whatsapp-campaigns.index')" class="btn btn-outline-secondary">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
