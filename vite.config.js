import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    define: {
        // Theme vendor scripts expect a global `templateName` identifier.
        // Inject it at build time so vendor modules don't crash in ESM.
        templateName: JSON.stringify('tryino'),
    },
    build: {
        rollupOptions: {
            output: {
                // Split heavy/long-lived vendor code into stable, separately cacheable
                // chunks so the per-page entry stays small on mobile.
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }
                    const norm = id.replace(/\\/g, '/');
                    if (/node_modules\/(motion-v|motion-dom|@motionone|popmotion|framer-motion|hey-listen|style-value-types)\//.test(norm)) {
                        return 'motion';
                    }
                    if (/node_modules\/(@vue|vue|@inertiajs|ziggy-js)\//.test(norm)) {
                        return 'vendor';
                    }
                    return undefined;
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/js/admin.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
