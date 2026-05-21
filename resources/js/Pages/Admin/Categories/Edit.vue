<script setup>
import CategoryFormFields from '@/Components/Admin/CategoryFormFields.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';

const props = defineProps({
    category: { type: Object, required: true },
    rootParents: { type: Array, required: true },
    hasChildren: { type: Boolean, default: false },
});

const form = useForm({
    parent_id: props.category.parent_id ?? '',
    name: props.category.name,
    slug: props.category.slug,
    meta_title: props.category.meta_title ?? '',
    meta_description: props.category.meta_description ?? '',
    og_image_url: props.category.og_image_url ?? '',
    intro_html: props.category.intro_html ?? '',
    sort_order: props.category.sort_order ?? 0,
    is_active: props.category.is_active !== false,
});

function submit() {
    form.put(route('admin.categories.update', props.category.id));
}
</script>

<template>
    <Head title="Admin — Edit category" />
    <AdminLayout>
        <AdminPageHeader
            title="Edit category"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Categories', href: route('admin.categories.index') }, { label: props.category.name }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Category details">
                <CategoryFormFields
                    :form="form"
                    :root-parents="rootParents"
                    :has-children="hasChildren"
                    is-edit
                />

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save changes</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.categories.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
