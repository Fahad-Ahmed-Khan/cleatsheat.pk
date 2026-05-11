<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSubmenu from './AdminSubmenu.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const hasChildren = computed(() => Array.isArray(props.item.children) && props.item.children.length > 0);
const isActive = computed(() => {
    if (!props.item.active) return false;
    try {
        return route().current(props.item.active);
    } catch {
        return false;
    }
});

function isActiveDeep(item) {
    if (!item) return false;
    if (item.active) {
        try {
            if (route().current(item.active)) return true;
        } catch {
            // ignore
        }
    }
    if (Array.isArray(item.children)) {
        return item.children.some((child) => isActiveDeep(child));
    }
    return false;
}

const isOpen = computed(() => (hasChildren.value ? isActiveDeep(props.item) : false));
</script>

<template>
    <li class="menu-item" :class="{ active: isActive, open: isOpen }">
        <template v-if="hasChildren">
            <a class="menu-link menu-toggle" href="javascript:void(0);">
                <i v-if="item.icon" class="menu-icon" :class="item.icon"></i>
                <div>{{ item.label }}</div>
            </a>
            <AdminSubmenu :items="item.children" />
        </template>
        <template v-else>
            <Link
                class="menu-link"
                :href="item.href"
                :target="item.target"
            >
                <i v-if="item.icon" class="menu-icon" :class="item.icon"></i>
                <div>{{ item.label }}</div>
            </Link>
        </template>
    </li>
</template>

