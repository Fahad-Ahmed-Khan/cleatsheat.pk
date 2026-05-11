const STORAGE_KEY = 'admin.theme';

export function getAdminTheme() {
    if (typeof window === 'undefined') return 'light';
    return window.localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
}

export function applyAdminTheme(theme) {
    if (typeof document === 'undefined') return;
    const t = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
    window.localStorage.setItem(STORAGE_KEY, t);
}

export function initAdminTheme() {
    applyAdminTheme(getAdminTheme());
}

export function toggleAdminTheme() {
    const next = getAdminTheme() === 'dark' ? 'light' : 'dark';
    applyAdminTheme(next);
    return next;
}
