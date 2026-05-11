<script setup>
import { onBeforeUnmount, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    brand: { type: Object, required: true },
});

const form = useForm({
    name: props.brand.name,
    slug: props.brand.slug,
    meta_title: props.brand.meta_title ?? '',
    meta_description: props.brand.meta_description ?? '',
    logo: null,
});

const logoPreview = ref(props.brand.logo_url ?? null);

function onPickLogo(e) {
    const file = e?.target?.files?.[0] ?? null;
    form.logo = file;
    if (file) {
        if (logoPreview.value && String(logoPreview.value).startsWith('blob:')) URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = URL.createObjectURL(file);
    }
}

onBeforeUnmount(() => {
    if (logoPreview.value && String(logoPreview.value).startsWith('blob:')) URL.revokeObjectURL(logoPreview.value);
});

function submit() {
    form.post(route('admin.brands.update', props.brand.id), { forceFormData: true, preserveScroll: true, _method: 'put' });
}
</script>

<template>
    <Head title="Admin — Edit brand" />
    <AdminLayout>
        <AdminPageHeader
            title="Edit brand"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Brands', href: route('admin.brands.index') }, { label: props.brand.name }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Brand details">
                <div class="mb-3">
                    <label class="form-label">Logo (optional)</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="border rounded bg-body-tertiary overflow-hidden" style="width: 72px; height: 72px;">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="w-100 h-100 object-fit-cover" />
                            <div v-else class="d-flex w-100 h-100 align-items-center justify-content-center text-muted small">
                                <i class="icon-base ti tabler-photo icon-22px"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" accept="image/*" class="form-control" @change="onPickLogo" />
                            <div class="form-text">Upload a new logo to replace the existing one.</div>
                            <div v-if="form.errors.logo" class="invalid-feedback d-block">{{ form.errors.logo }}</div>
                        </div>
                    </div>
                </div>

                <FormField id="brand_name" label="Name" :error="form.errors.name">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="brand_name"
                            v-model="form.name"
                            type="text"
                            required
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="brand_slug" label="Slug" :error="form.errors.slug">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="brand_slug"
                            v-model="form.slug"
                            type="text"
                            required
                            pattern="[a-z0-9-]+"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="brand_meta_title" label="Meta title" :error="form.errors.meta_title">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="brand_meta_title"
                            v-model="form.meta_title"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="brand_meta_description" label="Meta description" :error="form.errors.meta_description">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="brand_meta_description"
                            v-model="form.meta_description"
                            rows="2"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        Save
                    </button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.brands.index')">
                        Cancel
                    </Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
