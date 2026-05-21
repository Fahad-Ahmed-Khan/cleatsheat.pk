<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import AdminImageUploadField from '@/Components/Admin/AdminImageUploadField.vue';
import { storefrontSettingsPayload } from '@/admin/storefrontFormPayload.js';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    site_name: props.settings.site_name ?? '',
    logo: null,
    logo_url: props.settings.logo_url ?? '',
    logo_dark: null,
    logo_dark_url: props.settings.logo_dark_url ?? '',
    favicon: null,
    favicon_url: props.settings.favicon_url ?? '',
    primary_color: props.settings.primary_color ?? '#dfff00',
    secondary_color: props.settings.secondary_color ?? '#576500',
    primary_foreground_color: props.settings.primary_foreground_color ?? '#191e00',
    hero_title: props.settings.hero_title ?? '',
    hero_subtitle: props.settings.hero_subtitle ?? '',
    hero_badge: props.settings.hero_badge ?? '',
    hero_image: null,
    hero_image_url: props.settings.hero_image_url ?? '',
    hero_cta_label: props.settings.hero_cta_label ?? '',
    hero_cta_url: props.settings.hero_cta_url ?? '',
    promo_banner_image: null,
    promo_banner_image_url: props.settings.promo_banner_image_url ?? '',
    promo_banner_link_url: props.settings.promo_banner_link_url ?? '',
    promo_banner_title: props.settings.promo_banner_title ?? '',
    default_meta_title: props.settings.default_meta_title ?? '',
    default_meta_description: props.settings.default_meta_description ?? '',
    default_og_image: null,
    default_og_image_url: props.settings.default_og_image_url ?? '',
    twitter_site: props.settings.twitter_site ?? '',
    ga4_enabled: props.settings.ga4_enabled ?? false,
    ga4_measurement_id: props.settings.ga4_measurement_id ?? '',
    gtm_enabled: props.settings.gtm_enabled ?? false,
    gtm_container_id: props.settings.gtm_container_id ?? '',
    meta_pixel_enabled: props.settings.meta_pixel_enabled ?? false,
    meta_pixel_id: props.settings.meta_pixel_id ?? '',
    tiktok_pixel_enabled: props.settings.tiktok_pixel_enabled ?? false,
    tiktok_pixel_id: props.settings.tiktok_pixel_id ?? '',
});

function submit() {
    // Multipart file uploads must POST with Laravel method spoofing (PHP cannot parse PATCH + files).
    // Only include file keys when the user picked a File — otherwise Inertia sends "" and fails `image` validation.
    form
        .transform(storefrontSettingsPayload)
        .post(route('admin.storefront-settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
}
</script>

<template>
    <Head title="Admin — Storefront" />
    <AdminLayout>
        <AdminPageHeader
            title="Storefront"
            subtitle="Logo, brand colours, homepage hero, SEO defaults, and analytics tags."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Storefront' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Branding">
                <FormField id="sf_site_name" label="Store name (optional override)" :error="form.errors.site_name">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_site_name" v-model="form.site_name" class="form-control" placeholder="Leave blank to use app name" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <AdminImageUploadField
                    id="sf_logo"
                    label="Logo (light mode)"
                    :url="form.logo_url"
                    :error="form.errors.logo_url"
                    :file-error="form.errors.logo"
                    hint="PNG/SVG with transparent background recommended."
                    @update:url="form.logo_url = $event"
                    @file="form.logo = $event"
                />
                <AdminImageUploadField
                    id="sf_logo_dark"
                    label="Logo (dark mode, optional)"
                    :url="form.logo_dark_url"
                    :error="form.errors.logo_dark_url"
                    :file-error="form.errors.logo_dark"
                    @update:url="form.logo_dark_url = $event"
                    @file="form.logo_dark = $event"
                />
                <AdminImageUploadField
                    id="sf_favicon"
                    label="Favicon (optional)"
                    :url="form.favicon_url"
                    :error="form.errors.favicon_url"
                    :file-error="form.errors.favicon"
                    accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon,.ico"
                    preview-size="48px"
                    hint="Square PNG or ICO, ideally 32×32 or 64×64."
                    @update:url="form.favicon_url = $event"
                    @file="form.favicon = $event"
                />
                <div class="row g-3">
                    <div class="col-md-4">
                        <FormField id="sf_primary" label="Primary colour" :error="form.errors.primary_color">
                            <template #default="{ invalid, describedBy }">
                                <div class="d-flex gap-2 align-items-center">
                                    <input id="sf_primary_picker" v-model="form.primary_color" type="color" class="form-control form-control-color" />
                                    <input id="sf_primary" v-model="form.primary_color" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                </div>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="sf_secondary" label="Secondary colour" :error="form.errors.secondary_color">
                            <template #default="{ invalid, describedBy }">
                                <div class="d-flex gap-2 align-items-center">
                                    <input v-model="form.secondary_color" type="color" class="form-control form-control-color" />
                                    <input id="sf_secondary" v-model="form.secondary_color" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                </div>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-4">
                        <FormField id="sf_primary_fg" label="Primary text colour" :error="form.errors.primary_foreground_color" hint="Text on primary buttons and badges.">
                            <template #default="{ invalid, describedBy }">
                                <div class="d-flex gap-2 align-items-center">
                                    <input v-model="form.primary_foreground_color" type="color" class="form-control form-control-color" />
                                    <input id="sf_primary_fg" v-model="form.primary_foreground_color" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                                </div>
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Homepage hero">
                <FormField id="sf_hero_title" label="Hero title" :error="form.errors.hero_title">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_hero_title" v-model="form.hero_title" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="sf_hero_sub" label="Hero subtitle" :error="form.errors.hero_subtitle">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="sf_hero_sub" v-model="form.hero_subtitle" rows="3" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="sf_hero_badge" label="Hero badge" :error="form.errors.hero_badge">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_hero_badge" v-model="form.hero_badge" class="form-control" placeholder="New season" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <AdminImageUploadField
                    id="sf_hero_img"
                    label="Hero background image"
                    :url="form.hero_image_url"
                    :error="form.errors.hero_image_url"
                    :file-error="form.errors.hero_image"
                    preview-size="120px"
                    url-hint="Upload a wide image (e.g. 1920×800) or paste a URL."
                    @update:url="form.hero_image_url = $event"
                    @file="form.hero_image = $event"
                />
                <div class="row g-3">
                    <div class="col-md-6">
                        <FormField id="sf_hero_cta_label" label="Primary CTA label" :error="form.errors.hero_cta_label">
                            <template #default="{ invalid, describedBy }">
                                <input id="sf_hero_cta_label" v-model="form.hero_cta_label" class="form-control" placeholder="Shop FG boots" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <div class="col-md-6">
                        <FormField id="sf_hero_cta_url" label="Primary CTA URL" :error="form.errors.hero_cta_url">
                            <template #default="{ invalid, describedBy }">
                                <input id="sf_hero_cta_url" v-model="form.hero_cta_url" class="form-control" placeholder="/shop" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Promo banner (optional)">
                <FormField id="sf_promo_title" label="Banner title" :error="form.errors.promo_banner_title">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_promo_title" v-model="form.promo_banner_title" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <AdminImageUploadField
                    id="sf_promo_img"
                    label="Banner image"
                    :url="form.promo_banner_image_url"
                    :error="form.errors.promo_banner_image_url"
                    :file-error="form.errors.promo_banner_image"
                    preview-size="120px"
                    @update:url="form.promo_banner_image_url = $event"
                    @file="form.promo_banner_image = $event"
                />
                <FormField id="sf_promo_link" label="Banner link URL" :error="form.errors.promo_banner_link_url">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_promo_link" v-model="form.promo_banner_link_url" class="form-control" placeholder="/shop" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="SEO defaults">
                <FormField id="sf_meta_title" label="Default meta title" :error="form.errors.default_meta_title">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_meta_title" v-model="form.default_meta_title" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <FormField id="sf_meta_desc" label="Default meta description" :error="form.errors.default_meta_description">
                    <template #default="{ invalid, describedBy }">
                        <textarea id="sf_meta_desc" v-model="form.default_meta_description" rows="3" class="form-control" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <AdminImageUploadField
                    id="sf_og"
                    label="Default OG / social image"
                    :url="form.default_og_image_url"
                    :error="form.errors.default_og_image_url"
                    :file-error="form.errors.default_og_image"
                    preview-size="96px"
                    url-hint="Recommended 1200×630. Upload or paste a URL."
                    @update:url="form.default_og_image_url = $event"
                    @file="form.default_og_image = $event"
                />
                <FormField id="sf_twitter" label="Twitter @site handle" :error="form.errors.twitter_site">
                    <template #default="{ invalid, describedBy }">
                        <input id="sf_twitter" v-model="form.twitter_site" class="form-control" placeholder="@tryino" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>
            </FormSection>

            <FormSection title="Analytics & tags">
                <div class="form-check mb-2">
                    <input id="ga4Enabled" v-model="form.ga4_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ga4Enabled">Google Analytics 4</label>
                </div>
                <FormField id="ga4Id" label="GA4 measurement ID" :error="form.errors.ga4_measurement_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="ga4Id" v-model="form.ga4_measurement_id" class="form-control font-monospace" placeholder="G-XXXXXXXXXX" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="form-check mb-2 mt-3">
                    <input id="gtmEnabled" v-model="form.gtm_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="gtmEnabled">Google Tag Manager</label>
                </div>
                <FormField id="gtmId" label="GTM container ID" :error="form.errors.gtm_container_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="gtmId" v-model="form.gtm_container_id" class="form-control font-monospace" placeholder="GTM-XXXXXXX" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="form-check mb-2 mt-3">
                    <input id="metaEnabled" v-model="form.meta_pixel_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="metaEnabled">Meta (Facebook) Pixel</label>
                </div>
                <FormField id="metaId" label="Meta pixel ID" :error="form.errors.meta_pixel_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="metaId" v-model="form.meta_pixel_id" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="form-check mb-2 mt-3">
                    <input id="ttEnabled" v-model="form.tiktok_pixel_enabled" type="checkbox" class="form-check-input" />
                    <label class="form-check-label" for="ttEnabled">TikTok Pixel</label>
                </div>
                <FormField id="ttId" label="TikTok pixel ID" :error="form.errors.tiktok_pixel_id">
                    <template #default="{ invalid, describedBy }">
                        <input id="ttId" v-model="form.tiktok_pixel_id" class="form-control font-monospace" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save storefront settings</button>
                    <span v-if="form.recentlySuccessful" class="text-muted small ms-2">Saved.</span>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
