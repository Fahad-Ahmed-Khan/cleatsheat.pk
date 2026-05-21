<script setup>
import StoreSkeleton from '@/Components/Store/StoreSkeleton.vue';
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-3xl border border-stadium-outline-soft/30 bg-stadium-white p-0">
        <StoreSkeleton class="aspect-[4/5] w-full rounded-none" />
        <div class="space-y-2 p-4">
            <StoreSkeleton class="h-3 w-16" />
            <StoreSkeleton class="h-4 w-full" />
            <StoreSkeleton class="h-4 w-2/3" />
            <StoreSkeleton class="mt-2 h-6 w-20" />
        </div>
    </article>
</template>
