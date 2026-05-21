import { computed, ref } from 'vue';

const STORAGE_KEY = 'cleatsheat_wishlist';
const ids = ref([]);

function load() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        ids.value = raw ? JSON.parse(raw) : [];
        if (!Array.isArray(ids.value)) ids.value = [];
    } catch {
        ids.value = [];
    }
}

function save() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids.value));
    } catch {
        /* ignore quota */
    }
}

load();

export function useStoreWishlist() {
    function toggle(productId) {
        const id = Number(productId);
        const i = ids.value.indexOf(id);
        if (i >= 0) {
            ids.value = ids.value.filter((x) => x !== id);
        } else {
            ids.value = [...ids.value, id];
        }
        save();
    }

    function isWishlisted(productId) {
        return ids.value.includes(Number(productId));
    }

    const count = computed(() => ids.value.length);

    return { toggle, isWishlisted, count };
}
