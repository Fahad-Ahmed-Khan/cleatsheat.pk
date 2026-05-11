<script setup>
import DataTablePagination from './DataTablePagination.vue';
import EmptyState from './EmptyState.vue';

defineProps({
    title: { type: String, default: '' },
    paginator: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'No results' },
    emptyDescription: { type: String, default: '' },
});
</script>

<template>
    <div class="card">
        <div v-if="$slots.header" class="card-header p-0">
            <slot name="header" />
        </div>

        <div v-else-if="title || $slots.toolbar" class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 v-if="title" class="mb-0">{{ title }}</h5>
            <div class="d-flex align-items-center gap-2">
                <slot name="toolbar" />
            </div>
        </div>

        <div class="card-body p-0">
            <div v-if="loading" class="p-4 text-muted">
                Loading…
            </div>

            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <slot name="head" />
                        </tr>
                    </thead>
                    <tbody>
                        <slot name="body" />
                    </tbody>
                </table>
            </div>

            <div v-if="$slots.empty" class="p-4">
                <slot name="empty" />
            </div>
            <div
                v-else-if="!loading && paginator && Array.isArray(paginator.data) && paginator.data.length === 0"
                class="p-4"
            >
                <EmptyState :title="emptyTitle" :description="emptyDescription">
                    <template #actions>
                        <slot name="emptyActions" />
                    </template>
                </EmptyState>
            </div>
        </div>

        <div v-if="paginator?.links?.length" class="card-footer d-flex justify-content-end">
            <DataTablePagination :paginator="paginator" />
        </div>
    </div>
</template>

