<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

defineProps({
    posts: { type: Object, required: true },
});

function destroy(id) {
    if (!window.confirm('Delete this article?')) {
        return;
    }
    router.delete(route('admin.content-posts.destroy', id));
}
</script>

<template>
    <Head title="Admin — Journal articles" />
    <AdminLayout>
        <AdminPageHeader
            title="Journal articles"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Journal' }]"
        >
            <template #actions>
                <Link class="btn btn-primary btn-sm" :href="route('admin.content-posts.create')">
                    New article
                </Link>
            </template>
        </AdminPageHeader>

        <DataTable :paginator="posts" empty-title="No articles yet">
            <template #head>
                <th>Title</th>
                <th>Slug</th>
                <th>Published</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="p in posts.data" :key="p.id">
                    <td class="fw-semibold">{{ p.title }}</td>
                    <td><code>{{ p.slug }}</code></td>
                    <td><StatusBadge :status="p.is_published ? 'published' : 'draft'" /></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Article actions">
                            <Link class="btn btn-outline-primary" :href="route('admin.content-posts.edit', p.id)">
                                Edit
                            </Link>
                            <button type="button" class="btn btn-outline-danger" @click="destroy(p.id)">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
