<script setup>
import AdminUserDropdown from './AdminUserDropdown.vue';
import { ref } from 'vue';
import { getAdminTheme, toggleAdminTheme } from '@/admin/adminTheme';

const theme = ref(getAdminTheme());

function onToggleTheme() {
    theme.value = toggleAdminTheme();
}
</script>

<template>
    <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme">
        <div class="container-xxl">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <span class="fw-semibold">Tryino Admin</span>
            </a>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <div class="d-none d-md-block">
                    <input
                        type="search"
                        class="form-control"
                        placeholder="Search (coming soon)"
                        disabled
                    />
                </div>
                <button
                    type="button"
                    class="btn btn-icon btn-text-secondary"
                    :title="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                    @click="onToggleTheme"
                >
                    <i
                        v-if="theme === 'dark'"
                        class="icon-base ti tabler-sun icon-22px"
                    ></i>
                    <i
                        v-else
                        class="icon-base ti tabler-moon icon-22px"
                    ></i>
                </button>
                <AdminUserDropdown />
            </div>
        </div>
    </nav>
</template>

