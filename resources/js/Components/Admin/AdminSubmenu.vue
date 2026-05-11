<script setup>
import { Link } from '@inertiajs/vue3';

defineOptions({ name: 'AdminSubmenu' });

const props = defineProps({
    items: { type: Array, required: true },
});

function isActive(item) {
    if (!item?.active) return false;
    try {
        return route().current(item.active);
    } catch {
        return false;
    }
}

function isActiveDeep(item) {
    if (!item) return false;
    if (isActive(item)) return true;
    if (Array.isArray(item.children)) {
        return item.children.some((child) => isActiveDeep(child));
    }
    return false;
}

function hasChildren(item) {
    return Array.isArray(item?.children) && item.children.length > 0;
}
</script>

<template>
    <ul class="menu-sub">
        <li
            v-for="child in items"
            :key="child.label"
            class="menu-item"
            :class="{ active: isActive(child), open: hasChildren(child) && isActiveDeep(child) }"
        >
            <template v-if="hasChildren(child)">
                <a class="menu-link menu-toggle" href="javascript:void(0);">
                    <div>{{ child.label }}</div>
                </a>
                <AdminSubmenu :items="child.children" />
            </template>
            <template v-else>
                <Link class="menu-link" :href="child.href" :target="child.target">
                    <div>{{ child.label }}</div>
                </Link>
            </template>
        </li>
    </ul>
</template>

