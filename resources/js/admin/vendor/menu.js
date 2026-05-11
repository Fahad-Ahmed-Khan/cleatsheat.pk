import { Menu as VendorMenu } from '../../../../vue-admin-design/resources/assets/vendor/js/menu.js';

function safeRemove(node) {
    if (!node) return;
    const parent = node.parentNode;
    if (!parent) return;
    try {
        parent.removeChild(node);
    } catch {
        // ignore
    }
}

/**
 * The vendor `Menu` assumes its DOM is still mounted when `destroy()` runs.
 * In Inertia navigations, the DOM can be replaced first, so `parentNode` can be null.
 * Wrap destroy with defensive checks so admin navigation never crashes.
 */
class Menu extends VendorMenu {
    destroy() {
        try {
            super.destroy();
        } catch {
            // Best-effort cleanup without assuming DOM structure is still present.
            safeRemove(this?._prevBtn);
            safeRemove(this?._nextBtn);
            safeRemove(this?._wrapper);

            if (this?._scrollbar?.destroy) {
                try {
                    this._scrollbar.destroy();
                } catch {
                    // ignore
                }
            }

            if (this?._el) {
                try {
                    this._el.menuInstance = null;
                    // eslint-disable-next-line @typescript-eslint/no-dynamic-delete
                    delete this._el.menuInstance;
                } catch {
                    // ignore
                }
            }

            this._el = null;
            this._scrollbar = null;
            this._inner = null;
            this._prevBtn = null;
            this._wrapper = null;
            this._nextBtn = null;
        }
    }
}

export { Menu };

