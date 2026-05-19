<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const cartCount = computed(() => Number(page.props.cartCount ?? 0));
const isLoggedIn = computed(() => !!page.props.auth?.user);

const shopHref = computed(() => route('store.shop'));

const currentPath = computed(() => {
    const url = page.url || '';
    const noQuery = url.split('?')[0] || '';
    return noQuery.split('#')[0] || '/';
});

function isActive(predicate) {
    const path = currentPath.value;
    if (Array.isArray(predicate)) {
        return predicate.some((p) => isActive(p));
    }
    if (typeof predicate === 'function') {
        return predicate(path);
    }
    return path === predicate;
}

const accountHref = computed(() => (isLoggedIn.value ? route('dashboard') : route('login')));

const items = computed(() => [
    {
        key: 'home',
        label: 'Home',
        href: route('store.home'),
        active: isActive('/'),
        icon: 'home',
    },
    {
        key: 'shop',
        label: 'Shop',
        href: shopHref.value,
        active: isActive((p) => p === '/shop' || p.startsWith('/shop/') || p.startsWith('/c/') || p.startsWith('/p/')),
        icon: 'shop',
    },
    {
        key: 'cart',
        label: 'Cart',
        href: route('store.cart'),
        active: isActive((p) => p.startsWith('/cart')),
        icon: 'cart',
        badge: cartCount.value,
    },
    {
        key: 'track',
        label: 'Track',
        href: route('store.order-tracking'),
        active: isActive((p) => p.startsWith('/track-order')),
        icon: 'truck',
    },
    {
        key: 'account',
        label: 'Account',
        href: accountHref.value,
        active: isActive((p) => p.startsWith('/dashboard') || p.startsWith('/login') || p.startsWith('/orders')),
        icon: 'user',
    },
]);
</script>

<template>
    <nav
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-50 rounded-t-xl border-t border-stadium-outline-soft bg-stadium-white shadow-stadium-nav sm:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <ul class="mx-auto flex max-w-lg items-stretch justify-around px-1 pt-1">
            <li v-for="item in items" :key="item.key" class="flex-1">
                <Link
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="relative mb-1 flex min-h-[4.25rem] flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-bold uppercase tracking-wide transition active:scale-[0.97]"
                    :class="
                        item.active
                            ? 'bg-stadium-lime text-stadium-lime-ink shadow-sm'
                            : 'bg-stadium-muted text-stadium-ink hover:bg-stadium-container-high'
                    "
                >
                    <span class="relative inline-flex h-6 w-6 items-center justify-center">
                        <!-- Home -->
                        <svg
                            v-if="item.icon === 'home'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <path d="M3 11l9-7 9 7" />
                            <path d="M5 10v10h14V10" />
                            <path d="M10 20v-6h4v6" />
                        </svg>
                        <!-- Shop -->
                        <svg
                            v-else-if="item.icon === 'shop'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
                            <path d="M2 12h20" />
                        </svg>
                        <!-- Cart -->
                        <svg
                            v-else-if="item.icon === 'cart'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="20" r="1.5" />
                            <circle cx="17" cy="20" r="1.5" />
                            <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.5a2 2 0 0 0 2-1.5L21 8H6" />
                        </svg>
                        <!-- Truck -->
                        <svg
                            v-else-if="item.icon === 'truck'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <path d="M3 7h11v9H3z" />
                            <path d="M14 10h4l3 3v3h-7" />
                            <circle cx="7.5" cy="17.5" r="1.8" />
                            <circle cx="17.5" cy="17.5" r="1.8" />
                        </svg>
                        <!-- User -->
                        <svg
                            v-else
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="8" r="3.5" />
                            <path d="M4 20a8 8 0 0 1 16 0" />
                        </svg>

                        <span
                            v-if="item.badge && item.badge > 0"
                            class="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-stadium-ink px-1 text-[9px] font-bold leading-none text-stadium-lime ring-2 ring-stadium-white"
                        >
                            {{ item.badge > 99 ? '99+' : item.badge }}
                        </span>
                    </span>
                    <span>{{ item.label }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
