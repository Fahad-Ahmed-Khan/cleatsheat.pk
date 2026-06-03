import '../css/app.css';
import './bootstrap';

import { initStoreTheme } from './store/storeTheme';
import { bootInertiaApp } from './inertia/createInertia';

initStoreTheme();

bootInertiaApp({
    pageGlob: import.meta.glob([
        './Pages/Store/**/*.vue',
        './Pages/Auth/**/*.vue',
        './Pages/Profile/**/*.vue',
    ]),
});
