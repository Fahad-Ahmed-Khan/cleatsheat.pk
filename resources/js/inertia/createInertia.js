import { createInertiaApp, router } from '@inertiajs/vue3';
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
    }).then(() => {
        router.on('invalid', (event) => {
            const response = event.detail?.response;
            if (!response) {
                return;
            }

            if (response.status === 419) {
                event.preventDefault();
                window.alert('Your session expired. The page will reload — please sign in again.');
                window.location.reload();

                return;
            }

            if (response.status !== 409) {
                return;
            }

            const headers = response.headers ?? {};
            const location =
                headers['x-inertia-location'] ??
                headers['X-Inertia-Location'];

            if (location) {
                window.location.assign(location);
            }
        });
    });
}

