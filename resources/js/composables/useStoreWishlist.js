import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'cleatsheat_wishlist';
const ids = ref([]);
let mergeAttempted = false;

function loadLocal() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        ids.value = raw ? JSON.parse(raw) : [];
        if (!Array.isArray(ids.value)) ids.value = [];
    } catch {
        ids.value = [];
    }
}

function saveLocal() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids.value));
    } catch {
        /* ignore quota */
    }
}

function clearLocal() {
    ids.value = [];
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

function readLocalIds() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.map((x) => Number(x)).filter((x) => x > 0) : [];
    } catch {
        return [];
    }
}

function syncFromServer(serverIds) {
    ids.value = [...new Set(serverIds.map((x) => Number(x)).filter((x) => x > 0))];
}

function mergeGuestWishlist(user) {
    if (!user || mergeAttempted) return;
    const localIds = readLocalIds();
    if (localIds.length === 0) {
        mergeAttempted = true;
        return;
    }
    mergeAttempted = true;
    router.post(
        route('store.account.wishlist.merge'),
        { product_ids: localIds },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => clearLocal(),
        },
    );
}

loadLocal();

export function useStoreWishlist() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user ?? null);
    const serverIds = computed(() => page.props.wishlistProductIds ?? []);

    watch(
        [user, serverIds],
        ([u, sids]) => {
            if (u) {
                syncFromServer(sids);
                mergeGuestWishlist(u);
            } else {
                mergeAttempted = false;
                loadLocal();
            }
        },
        { immediate: true },
    );

    function toggle(productId) {
        const id = Number(productId);
        if (user.value) {
            if (ids.value.includes(id)) {
                router.delete(route('store.account.wishlist.destroy', id), {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        ids.value = ids.value.filter((x) => x !== id);
                    },
                });
            } else {
                router.post(route('store.account.wishlist.store', id), {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        ids.value = [...ids.value, id];
                    },
                });
            }
            return;
        }

        const i = ids.value.indexOf(id);
        if (i >= 0) {
            ids.value = ids.value.filter((x) => x !== id);
        } else {
            ids.value = [...ids.value, id];
        }
        saveLocal();
    }

    function isWishlisted(productId) {
        return ids.value.includes(Number(productId));
    }

    const count = computed(() => ids.value.length);

    return { toggle, isWishlisted, count };
}
