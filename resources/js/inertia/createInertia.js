import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../../vendor/tightenco/ziggy';

export function bootInertiaApp({
    pageGlob,
    appName = import.meta.env.VITE_APP_NAME || 'Laravel',
    progressColor = '#4B5563',
} = {}) {
    if (!pageGlob) {
        throw new Error('bootInertiaApp requires a pageGlob from app.js or admin.js');
    }
    return createInertiaApp({
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => {
            // `pageGlob` keys depend on where `import.meta.glob()` was invoked from.
            // Support both common shapes:
            // - `../Pages/...` (when globbed from `resources/js/inertia/createInertia.js`)
            // - `./Pages/...`  (when globbed from `resources/js/app.js` / `resources/js/admin.js`)
            const candidates = [`../Pages/${name}.vue`, `./Pages/${name}.vue`];

            for (const candidate of candidates) {
                if (Object.prototype.hasOwnProperty.call(pageGlob, candidate)) {
                    return resolvePageComponent(candidate, pageGlob);
                }
            }

            // Fallback to original behavior for any other key shape.
            return resolvePageComponent(`../Pages/${name}.vue`, pageGlob);
        },
        setup({ el, App, props, plugin }) {
            return createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue)
                .mount(el);
        },
        progress: {
            color: progressColor,
        },
    });
}

