import { Helpers } from '../../../../vue-admin-design/resources/assets/vendor/js/helpers.js';

// Theme vendor modules expect `window.Helpers` to exist (not ESM imports).
if (typeof window !== 'undefined') {
    window.Helpers = Helpers;
}

export { Helpers };

