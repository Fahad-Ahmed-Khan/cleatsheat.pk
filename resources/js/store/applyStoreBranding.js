/**
 * Apply storefront brand colours from Inertia shared props as CSS variables.
 */
export function applyStoreBranding(branding) {
    if (typeof document === 'undefined' || !branding?.theme) {
        return;
    }

    const { primary, secondary, primaryForeground } = branding.theme;
    const root = document.documentElement;

    if (primary) {
        root.style.setProperty('--store-primary', primary);
    }
    if (secondary) {
        root.style.setProperty('--store-secondary', secondary);
    }
    if (primaryForeground) {
        root.style.setProperty('--store-primary-fg', primaryForeground);
    }

    if (branding.favicon_url) {
        let link = document.querySelector('link[rel="icon"][data-store-branding]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            link.setAttribute('data-store-branding', '1');
            document.head.appendChild(link);
        }
        link.href = branding.favicon_url;
    }
}
