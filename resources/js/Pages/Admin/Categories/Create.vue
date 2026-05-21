<script setup>
import CategoryFormFields from '@/Components/Admin/CategoryFormFields.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';

const props = defineProps({
    rootParents: { type: Array, required: true },
    presetParentId: { type: Number, default: null },
    defaultKind: { type: String, default: 'parent' },
});

const form = useForm({
    parent_id: props.presetParentId ?? '',
    name: '',
    slug: '',
    meta_title: '',
    meta_description: '',
    og_image_url: '',
    intro_html: '',
    sort_order: 0,
    is_active: true,
});

function submit() {
    form.post(route('admin.categories.store'));
}
</script>

<template>
    <Head title="Admin — New category" />
    <AdminLayout>
        <AdminPageHeader
            title="New category"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Categories', href: route('admin.categories.index') }, { label: 'New' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Category details">
                <CategoryFormFields
                    :form="form"
                    :root-parents="rootParents"
                    :preset-parent-id="presetParentId"
                    :default-kind="defaultKind"
                />

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create category</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.categories.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
