<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-dvh bg-slate-950 text-slate-100">
        <div class="border-b border-slate-800">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <span class="text-sm font-semibold tracking-tight">Tryino Admin</span>
                <nav class="flex max-w-full flex-wrap gap-2 text-xs">
                    <Link
                        :href="route('admin.dashboard')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Overview
                    </Link>
                    <Link
                        :href="route('admin.products.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Products
                    </Link>
                    <Link
                        :href="route('admin.brands.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Brands
                    </Link>
                    <Link
                        :href="route('admin.categories.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Categories
                    </Link>
                    <Link
                        :href="route('admin.colors.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Colors
                    </Link>
                    <Link
                        :href="route('admin.orders.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Orders
                    </Link>
                    <Link
                        :href="route('admin.size-charts.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Size charts
                    </Link>
                    <Link
                        :href="route('admin.couriers.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Couriers
                    </Link>
                    <Link
                        :href="route('admin.coupons.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Coupons
                    </Link>
                    <Link
                        :href="route('admin.payment-settings.edit')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Payments
                    </Link>
                    <Link
                        :href="route('admin.shipping-settings.edit')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Shipping
                    </Link>
                    <Link
                        :href="route('admin.whatsapp-settings.edit')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        WhatsApp
                    </Link>
                    <Link
                        :href="route('admin.marketing-settings.edit')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Marketing
                    </Link>
                    <Link
                        :href="route('admin.content-posts.index')"
                        class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-800"
                    >
                        Journal
                    </Link>
                    <Link
                        :href="route('store.home')"
                        class="rounded-md px-2 py-1 text-slate-400 hover:text-white"
                    >
                        Storefront
                    </Link>
                </nav>
            </div>
        </div>
        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>

