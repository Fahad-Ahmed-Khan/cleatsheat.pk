import { Menu as VendorMenu } from '../../../vendor/admin-theme/js/menu.js';

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
    /**
     * Vendor switchMenu() moves `.app-brand` and toggles vertical layout chrome that
     * our Inertia admin shell does not render. Without those nodes, insertBefore(null)
     * throws and breaks navigation (e.g. Settings → Storefront).
     */
    switchMenu(menu) {
        const brand = document.querySelector('.app-brand');
        const navbarCollapse = document.querySelector('#navbar-collapse');

        if (!brand || !navbarCollapse) {
            this._bindEvents();
            return;
        }

        super.switchMenu(menu);
    }

    /**
     * Horizontal theme menu: vendor scrolls `.menu-inner` (negative margin) when a
     * dropdown opens so the toggled item is aligned — that jumps the strip left and
     * hides earlier links on hover. Skip that auto-slide; keep submenu flip/margins.
     */
    _toggleDropdown(show, item, closeChildren) {
        const menu = VendorMenu._findMenu(item);
        const actualItem = item;
        let subMenuItem = false;

        if (show) {
            if (VendorMenu._findParent(item, 'menu-sub', false)) {
                subMenuItem = true;
                item = this._topParent ? this._topParent.parentNode : item;
            }

            const wrapperWidth = Math.round(this._wrapper.getBoundingClientRect().width);
            const itemOffset = this._getItemOffset(item);
            const itemWidth = Math.round(item.getBoundingClientRect().width);

            actualItem.classList.add('open');

            const menuWidth = Math.round(menu.getBoundingClientRect().width);

            if (subMenuItem) {
                if (
                    itemOffset + this._innerPosition + menuWidth * 2 > wrapperWidth &&
                    menuWidth < wrapperWidth &&
                    menuWidth >= itemWidth
                ) {
                    menu.style.left = [this._rtl ? '100%' : '-100%'];
                }
            } else if (
                itemOffset + this._innerPosition + menuWidth > wrapperWidth &&
                menuWidth < wrapperWidth &&
                menuWidth > itemWidth
            ) {
                menu.style[this._rtl ? 'marginRight' : 'marginLeft'] = `-${menuWidth - itemWidth}px`;
            }

            this._closeOther(actualItem, closeChildren);
            this._updateSlider();
        } else {
            const toggle = VendorMenu._findChild(item, ['menu-toggle']);

            toggle.length && toggle[0].removeAttribute('data-hover', 'true');
            item.classList.remove('open');
            menu.style[this._rtl ? 'marginRight' : 'marginLeft'] = null;

            if (closeChildren) {
                const opened = menu.querySelectorAll('.menu-item.open');

                for (let i = 0, l = opened.length; i < l; i++) opened[i].classList.remove('open');
            }
        }
    }

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

