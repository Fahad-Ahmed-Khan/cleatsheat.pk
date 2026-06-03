import { Helpers } from '../../../vendor/admin-theme/js/helpers.js';

// Theme vendor modules expect `window.Helpers` to exist (not ESM imports).
if (typeof window !== 'undefined') {
    window.Helpers = Helpers;
}

export { Helpers };

