const STORAGE_KEY = 'store.theme';

/** @returns {'dark'|'light'} */
export function getStoreTheme() {
    if (typeof window === 'undefined') {
        return 'dark';
    }
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === 'light') {
        return 'light';
    }

    return 'dark';
}

export function applyStoreTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    window.localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
}

export function initStoreTheme() {
    applyStoreTheme(getStoreTheme());
}

export function toggleStoreTheme() {
    const next = getStoreTheme() === 'dark' ? 'light' : 'dark';
    applyStoreTheme(next);
    return next;
}
