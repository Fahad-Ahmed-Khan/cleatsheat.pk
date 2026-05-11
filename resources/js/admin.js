import '../css/admin.scss';
import '../../vue-admin-design/resources/assets/vendor/fonts/iconify/iconify.css';
import 'sweetalert2/dist/sweetalert2.min.css';

import 'bootstrap';

// Theme vendor JS (scoped to admin pages by entry split)
import './admin/vendor/helpers';
import './admin/vendor/menu';
import './admin/vendor/dropdown-hover';

import { initAdminTheme } from './admin/adminTheme';
import { bootInertiaApp } from './inertia/createInertia';

initAdminTheme();

bootInertiaApp({
    pageGlob: import.meta.glob('./Pages/**/*.vue'),
    progressColor: '#6366F1',
});

