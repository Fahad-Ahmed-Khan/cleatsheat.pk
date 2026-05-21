<script setup>
import { Link } from '@inertiajs/vue3';

const links = [
    { label: 'Overview', route: 'store.account.dashboard', match: 'store.account.dashboard' },
    { label: 'Orders', route: 'store.account.orders.index', match: ['store.account.orders.index', 'store.account.orders.show'] },
    { label: 'Wishlist', route: 'store.account.wishlist', match: 'store.account.wishlist' },
    { label: 'Addresses', route: 'store.account.addresses', match: 'store.account.addresses' },
    { label: 'Profile', route: 'store.account.profile', match: 'store.account.profile' },
    { label: 'Password', route: 'store.account.password', match: 'store.account.password' },
];

function isActive(match) {
    if (Array.isArray(match)) {
        return match.some((m) => route().current(m));
    }
    return route().current(match);
}

function linkClass(active) {
    return [
        'block rounded-xl px-4 py-3 text-sm font-semibold transition',
        active
            ? 'bg-store-primary/15 text-stadium-ink ring-1 ring-store-primary/30'
            : 'text-stadium-secondary hover:bg-stadium-muted hover:text-stadium-ink',
    ];
}
</script>

<template>
    <nav class="space-y-1" aria-label="Account">
        <Link
            v-for="item in links"
            :key="item.route"
            :href="route(item.route)"
            :class="linkClass(isActive(item.match))"
        >
            {{ item.label }}
        </Link>
        <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="mt-4 block w-full rounded-xl px-4 py-3 text-left text-sm font-semibold text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
        >
            Sign out
        </Link>
    </nav>
</template>
