<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayoutLegacy from './AdminLayoutLegacy.vue';
import AdminNavbar from '@/Components/Admin/AdminNavbar.vue';
import AdminHorizontalMenu from '@/Components/Admin/AdminHorizontalMenu.vue';
import AdminFooter from '@/Components/Admin/AdminFooter.vue';
import Toast from '@/Components/Admin/Toast.vue';
import { useFlash } from '@/admin/useFlash';
import { Menu } from '@/admin/vendor/menu';

const url = computed(() => usePage().url || '');
const isLegacy = computed(() => {
    const query = url.value.includes('?') ? url.value.split('?')[1] : '';
    const params = new URLSearchParams(query);
    return params.get('admin_legacy') === '1';
});

const menuInstance = ref(null);
const { toasts, dismiss } = useFlash();

function initMenu() {
    const el = document.querySelector('#layout-menu');
    if (!el) return;

    // In Inertia navigations, the previous Menu's DOM may already be removed.
    // Destroy should never be allowed to crash navigation.
    if (menuInstance.value?.destroy) {
        try {
            menuInstance.value.destroy();
        } catch {
            // ignore
        }
    }
    menuInstance.value = new Menu(el, { orientation: 'horizontal', showDropdownOnHover: true });
}

onMounted(() => {
    initMenu();
    router.on('navigate', () => {
        // Inertia may replace DOM; re-init menu bindings
        initMenu();
    });
});

onBeforeUnmount(() => {
    if (menuInstance.value?.destroy) {
        try {
            menuInstance.value.destroy();
        } catch {
            // ignore
        }
    }
    menuInstance.value = null;
});
</script>

<template>
    <AdminLayoutLegacy v-if="isLegacy">
        <slot />
    </AdminLayoutLegacy>

    <div
        v-else
        class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu"
    >
        <div class="layout-container">
            <AdminNavbar />

            <div class="layout-page">
                <div class="content-wrapper">
                    <AdminHorizontalMenu />

                    <div class="container-xxl flex-grow-1 container-p-y">
                        <slot />
                    </div>

                    <AdminFooter right-text="v1" />

                    <div class="content-backdrop fade"></div>
                </div>
            </div>

            <div class="layout-overlay layout-menu-toggle"></div>
            <div class="drag-target"></div>
        </div>

        <Toast :toasts="toasts" @dismiss="dismiss" />
    </div>
</template>
