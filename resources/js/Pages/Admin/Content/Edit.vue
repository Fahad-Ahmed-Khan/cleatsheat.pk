<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    post: { type: Object, required: true },
});

const form = useForm({
    slug: props.post.slug,
    title: props.post.title,
    meta_title: props.post.meta_title ?? '',
    meta_description: props.post.meta_description ?? '',
    excerpt: props.post.excerpt ?? '',
    featured_image_url: props.post.featured_image_url ?? '',
    body: props.post.body ?? '',
    pillar_keyword: props.post.pillar_keyword ?? '',
    is_published: props.post.is_published ?? false,
    published_at: props.post.published_at_local ?? '',
});

function submit() {
    form.put(route('admin.content-posts.update', props.post.id));
}
</script>

<template>
    <Head title="Admin — Edit article" />
    <AdminLayout>
        <AdminPageHeader
            title="Edit article"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Journal', href: route('admin.content-posts.index') }, { label: form.title || 'Article' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Article">
                <FormField id="post_slug" label="Slug" :error="form.errors.slug">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_slug"
                            v-model="form.slug"
                            required
                            pattern="[a-z0-9-]+"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_title" label="Title" :error="form.errors.title">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_title"
                            v-model="form.title"
                            required
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_pillar" label="Pillar keyword" :error="form.errors.pillar_keyword">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_pillar"
                            v-model="form.pillar_keyword"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_meta_title" label="Meta title" :error="form.errors.meta_title">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_meta_title"
                            v-model="form.meta_title"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_meta_desc" label="Meta description" :error="form.errors.meta_description">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="post_meta_desc"
                            v-model="form.meta_description"
                            rows="2"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_excerpt" label="Excerpt" :error="form.errors.excerpt">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="post_excerpt"
                            v-model="form.excerpt"
                            rows="2"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="post_featured_image" label="Featured image URL" :error="form.errors.featured_image_url" hint="Used for social sharing and article schema (1200×630 recommended).">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_featured_image"
                            v-model="form.featured_image_url"
                            type="url"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <div v-if="form.featured_image_url" class="mb-3">
                    <img :src="form.featured_image_url" alt="Featured image preview" class="img-fluid rounded border" style="max-height: 12rem;">
                </div>

                <FormField id="post_body" label="Body (HTML)" :error="form.errors.body">
                    <template #default="{ invalid, describedBy }">
                        <textarea
                            id="post_body"
                            v-model="form.body"
                            rows="12"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <div class="form-check mb-3">
                    <input id="post_published" v-model="form.is_published" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="post_published">Published</label>
                </div>

                <FormField id="post_publish_at" label="Publish at" :error="form.errors.published_at">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="post_publish_at"
                            v-model="form.published_at"
                            type="datetime-local"
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.content-posts.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
