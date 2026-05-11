<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    paginator: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="paginator?.links?.length" aria-label="Pagination">
        <ul class="pagination mb-0">
            <li
                v-for="(link, idx) in paginator.links"
                :key="idx"
                class="page-item"
                :class="{ active: link.active, disabled: !link.url }"
            >
                <Link
                    v-if="link.url"
                    class="page-link"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    v-html="link.label"
                />
                <span v-else class="page-link" v-html="link.label" />
            </li>
        </ul>
    </nav>
</template>

