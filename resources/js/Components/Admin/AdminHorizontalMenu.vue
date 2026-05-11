<script setup>
import { computed } from 'vue';
import { buildAdminMenu } from '@/admin/menu';
import AdminMenuItem from './AdminMenuItem.vue';

const menu = computed(() => buildAdminMenu(route));
</script>

<template>
    <aside
        id="layout-menu"
        class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0"
        role="navigation"
        aria-label="Admin"
    >
        <!-- Match theme structure: Menu JS injects wrapper + prev/next here -->
        <div class="container-xxl d-flex h-100">
            <ul class="menu-inner">
                <AdminMenuItem v-for="item in menu" :key="item.label" :item="item" />
            </ul>
        </div>
    </aside>
</template>

