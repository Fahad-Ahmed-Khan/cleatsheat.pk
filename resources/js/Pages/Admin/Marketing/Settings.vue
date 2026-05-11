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
    home_meta_title: props.settings.home_meta_title ?? '',
    home_meta_description: props.settings.home_meta_description ?? '',
    default_og_image_url: props.settings.default_og_image_url ?? '',
    twitter_site: props.settings.twitter_site ?? '',
    ga4_enabled: props.settings.ga4_enabled ?? false,
    ga4_measurement_id: props.settings.ga4_measurement_id ?? '',
    meta_pixel_enabled: props.settings.meta_pixel_enabled ?? false,
    meta_pixel_id: props.settings.meta_pixel_id ?? '',
    tiktok_pixel_enabled: props.settings.tiktok_pixel_enabled ?? false,
    tiktok_pixel_id: props.settings.tiktok_pixel_id ?? '',
    robots_mode: props.settings.robots_mode ?? 'allow_all',
    robots_custom: props.settings.robots_custom ?? '',
});

function submit() {
    form.patch(route('admin.marketing-settings.update'));
}
</script>

<template>
    <Head title="Admin — Marketing & SEO" />
    <AdminLayout>
        <AdminPageHeader
            title="Marketing & SEO"
            subtitle="Pixels load on the storefront only when enabled."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Marketing & SEO' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Homepage meta">
                <FormField id="m_home_title" label="Home title override" :error="form.errors.home_meta_title">
                    <template #default="{ invalid, describedBy }">
                        <input id="m_home_title" v-model="form.home_meta_title" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="m_home_desc" label="Home meta description" :error="form.errors.home_meta_description">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="m_home_desc" v-model="form.home_meta_description" rows="3" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="m_og" label="Default OG / Twitter image URL" :error="form.errors.default_og_image_url">
                    <template #default="{ invalid, describedBy }">
                        <input id="m_og" v-model="form.default_og_image_url" class="form-control" placeholder="https://..." :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="m_twitter" label="Twitter @site handle (optional)" :error="form.errors.twitter_site">
                    <template #default="{ invalid, describedBy }">
                        <input id="m_twitter" v-model="form.twitter_site" class="form-control" placeholder="@tryino" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Pixels">
                <div class="form-check mb-2">
                    <input id="ga4Enabled" v-model="form.ga4_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ga4Enabled">Google Analytics 4</label>
                </div>
                <FormField id="ga4Id" label="GA4 measurement ID" :error="form.errors.ga4_measurement_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="ga4Id" v-model="form.ga4_measurement_id" class="form-control font-monospace" placeholder="G-XXXXXXXXXX" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="form-check mb-2">
                    <input id="metaEnabled" v-model="form.meta_pixel_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="metaEnabled">Meta (Facebook) Pixel</label>
                </div>
                <FormField id="metaId" label="Meta pixel ID" :error="form.errors.meta_pixel_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="metaId" v-model="form.meta_pixel_id" class="form-control font-monospace" placeholder="Numeric pixel ID" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="form-check mb-2">
                    <input id="ttEnabled" v-model="form.tiktok_pixel_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ttEnabled">TikTok Pixel</label>
                </div>
                <FormField id="ttId" label="TikTok pixel ID" :error="form.errors.tiktok_pixel_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="ttId" v-model="form.tiktok_pixel_id" class="form-control font-monospace" placeholder="TikTok pixel ID" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="robots.txt">
                <div class="form-check mb-2">
                    <input id="robotsAllow" v-model="form.robots_mode" type="radio" value="allow_all" class="form-check-input" />
                    <label class="form-check-label" for="robotsAllow">Allow all crawlers (default)</label>
                </div>
                <div class="form-check mb-3">
                    <input id="robotsCustom" v-model="form.robots_mode" type="radio" value="custom" class="form-check-input" />
                    <label class="form-check-label" for="robotsCustom">Custom body</label>
                </div>
                <FormField id="robotsBody" label="Custom robots body" :error="form.errors.robots_custom" hint="Used only when robots mode is Custom.">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="robotsBody" v-model="form.robots_custom" rows="8" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                    <span v-if="form.recentlySuccessful" class="text-muted small">Saved.</span>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
