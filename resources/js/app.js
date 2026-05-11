import '../css/app.css';
import './bootstrap';

import { bootInertiaApp } from './inertia/createInertia';

bootInertiaApp({
    pageGlob: import.meta.glob('./Pages/**/*.vue'),
});
