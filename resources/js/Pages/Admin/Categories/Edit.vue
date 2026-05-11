<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    category: { type: Object, required: true },
    parents: { type: Array, required: true },
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
            <FormSection title="Category">
                <FormField id="cat_parent" label="Parent (optional)" :error="form.errors.parent_id">
                    <template #default="{ invalid, describedBy }">
                        <select
                            id="cat_parent"
                            v-model="form.parent_id"
                            class="form-select"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        >
                            <option value="">None (root)</option>
                            <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </template>
                </FormField>

                <FormField id="cat_name" label="Name" :error="form.errors.name">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_name"
                            v-model="form.name"
                            required
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_slug" label="Slug" :error="form.errors.slug">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_slug"
                            v-model="form.slug"
                            required
                            pattern="[a-z0-9-]+"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_sort" label="Sort order" :error="form.errors.sort_order">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_sort"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_meta_title" label="Meta title" :error="form.errors.meta_title">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_meta_title"
                            v-model="form.meta_title"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_meta_desc" label="Meta description" :error="form.errors.meta_description">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="cat_meta_desc"
                            v-model="form.meta_description"
                            rows="2"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_og" label="OG image URL" :error="form.errors.og_image_url">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_og"
                            v-model="form.og_image_url"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="cat_intro" label="Category intro (HTML)" :error="form.errors.intro_html">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="cat_intro"
                            v-model="form.intro_html"
                            rows="4"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.categories.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
