<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="dropdown">
        <button
            class="btn btn-icon btn-text-secondary dropdown-toggle hide-arrow"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            <i class="icon-base ti tabler-user-circle icon-22px"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li v-if="user">
                <h6 class="dropdown-header">
                    {{ user.name }}
                    <div class="small text-muted">{{ user.email }}</div>
                </h6>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
                <Link class="dropdown-item" :href="route('profile.edit')">
                    <i class="icon-base ti tabler-user me-2"></i>
                    Profile
                </Link>
            </li>
            <li>
                <Link class="dropdown-item" :href="route('logout')" method="post" as="button">
                    <i class="icon-base ti tabler-logout me-2"></i>
                    Logout
                </Link>
            </li>
        </ul>
    </div>
</template>

