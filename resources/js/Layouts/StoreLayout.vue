<script setup>
import StoreBottomNav from '@/Components/Store/StoreBottomNav.vue';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const page = usePage();
const navCategories = page.props.navCategories ?? [];
const storefront = computed(() => page.props.storefront ?? {});
const flashPaymentError = computed(() => page.props.flashPaymentError);

const analytics = useStoreAnalytics();

let finishCount = 0;

function bindBargainHelper() {
    window.tryinoTrack = {
        bargainAccepted: (p) => analytics.trackBargainAccepted(p),
    };
}

function onInertiaFinish() {
    bindBargainHelper();
    finishCount += 1;
    if (finishCount > 1) {
        analytics.trackPageView();
    }
}

onMounted(() => {
    bindBargainHelper();
    analytics.trackPageView();
    document.addEventListener('inertia:finish', onInertiaFinish);
});

onUnmounted(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    delete window.tryinoTrack;
});
</script>

<template>
    <div class="min-h-dvh bg-stone-50 text-stone-900">
        <p
            v-if="flashPaymentError"
            class="border-b border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800"
            role="alert"
        >
            {{ flashPaymentError }}
        </p>
        <header
            class="sticky top-0 z-40 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur-md"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3"
            >
                <Link
                    :href="route('store.home')"
                    class="text-sm font-semibold tracking-tight text-stone-900"
                >
                    Tryino
                </Link>
                <nav
                    class="hidden flex-1 items-center justify-center gap-1 sm:flex"
                >
                    <template v-for="c in navCategories" :key="c.id">
                        <Link
                            v-if="c.slug"
                            :href="route('store.category', c.slug)"
                            class="rounded-full px-3 py-1.5 text-sm text-stone-600 transition hover:bg-stone-200/60 hover:text-stone-900"
                        >
                            {{ c.name }}
                        </Link>
                    </template>
                </nav>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('store.cart')"
                        class="text-sm font-medium text-stone-800"
                    >
                        Bag
                    </Link>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="route('login')"
                        class="text-sm text-stone-600"
                    >
                        Log in
                    </Link>
                    <Link
                        v-else
                        :href="route('dashboard')"
                        class="text-sm text-stone-600"
                    >
                        Account
                    </Link>
                </div>
            </div>
            <div
                class="flex gap-1 overflow-x-auto border-t border-stone-200/60 px-4 py-2 sm:hidden"
            >
                <template v-for="c in navCategories" :key="c.id">
                    <Link
                        v-if="c.slug"
                        :href="route('store.category', c.slug)"
                        class="shrink-0 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm ring-1 ring-stone-200/80"
                    >
                        {{ c.name }}
                    </Link>
                </template>
            </div>
        </header>
        <main class="pb-[calc(4rem+env(safe-area-inset-bottom))] sm:pb-0">
            <slot />
        </main>
        <a
            v-if="storefront.support_whatsapp_url && storefront.support_whatsapp_url !== '#'"
            :href="storefront.support_whatsapp_url"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-36 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-xl text-white shadow-lg ring-2 ring-white/30 transition duration-200 hover:scale-105 hover:bg-emerald-500 sm:bottom-8 sm:h-14 sm:w-14 sm:text-2xl"
            aria-label="Chat on WhatsApp"
        >
            <span aria-hidden="true">💬</span>
        </a>
        <StoreBottomNav />
        <footer class="mt-12 border-t border-stone-200 bg-white py-8 text-center text-xs text-stone-500">
            <Link :href="route('store.journal.index')" class="font-medium text-stone-700 underline-offset-2 hover:underline">
                Journal
            </Link>
            <span class="mx-2 text-stone-300">·</span>
            <Link :href="route('store.order-tracking')" class="font-medium text-stone-700 underline-offset-2 hover:underline">
                Track order
            </Link>
            <span class="mx-2 text-stone-300">·</span>
            © {{ new Date().getFullYear() }} Tryino · Pakistan
        </footer>
    </div>
</template>
