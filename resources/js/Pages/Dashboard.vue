<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="space-y-4 p-6 text-gray-900">
                        <p>You're logged in!</p>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <Link
                                :href="route('store.home')"
                                class="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Continue shopping
                            </Link>
                            <Link
                                v-if="isAdmin"
                                :href="route('admin.dashboard')"
                                class="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Admin panel
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
