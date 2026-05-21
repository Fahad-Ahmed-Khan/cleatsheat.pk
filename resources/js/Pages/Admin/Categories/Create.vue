<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    parents: { type: Array, required: true },
});

const form = useForm({
    parent_id: '',
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

                <div class="mb-3">
                    <div class="form-check">
                        <input id="cat_active" v-model="form.is_active" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="cat_active">Active (visible on storefront)</label>
                    </div>
                    <div v-if="form.errors.is_active" class="invalid-feedback d-block">{{ form.errors.is_active }}</div>
                </div>

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

                <FormField
                    id="cat_og"
                    label="Category image URL (optional)"
                    hint="Shown on the home surface tiles and used as the social (OG) preview image."
                    :error="form.errors.og_image_url"
                >
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="cat_og"
                            v-model="form.og_image_url"
                            type="text"
                            class="form-control"
                            placeholder="https://..."
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
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.categories.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
