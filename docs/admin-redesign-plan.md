# Admin redesign plan — `vue-admin-design` horizontal layout

> Scope: redesign the Tryino admin UI on top of the **horizontal navbar layout** from the bundled `vue-admin-design/` theme, **without** changing routes, controllers, request validation, or service behavior. All work happens in `resources/js/`, `resources/css/`, and (optionally) a new admin-only Vite entry. The reference theme is **Blade + Bootstrap**; we translate it into **Vue 3 SFCs** consumed by Inertia.

---

## 1. Inventory of current admin

### 1.1 Admin routes (`routes/admin.php`, prefix `admin`, name `admin.`, middleware `auth + admin`)

All routes inferred from `routes/admin.php` lines 19–61 plus controller bodies.

| Method(s) | URL | Named route | Controller@method | Inertia page (`Pages/Admin/...`) |
| --- | --- | --- | --- | --- |
| GET | `/admin` | `admin.dashboard` | `DashboardController@__invoke` | `Dashboard.vue` |
| GET | `/admin/brands` | `admin.brands.index` | `BrandAdminController@index` | `Brands/Index.vue` |
| GET | `/admin/brands/create` | `admin.brands.create` | `BrandAdminController@create` | `Brands/Create.vue` |
| POST | `/admin/brands` | `admin.brands.store` | `BrandAdminController@store` | redirect → index |
| GET | `/admin/brands/{brand}/edit` | `admin.brands.edit` | `BrandAdminController@edit` | `Brands/Edit.vue` |
| PUT/PATCH | `/admin/brands/{brand}` | `admin.brands.update` | `BrandAdminController@update` | redirect → index |
| DELETE | `/admin/brands/{brand}` | `admin.brands.destroy` | `BrandAdminController@destroy` | redirect → index |
| GET | `/admin/categories` | `admin.categories.index` | `CategoryAdminController@index` | `Categories/Index.vue` |
| GET | `/admin/categories/create` | `admin.categories.create` | `CategoryAdminController@create` | `Categories/Create.vue` |
| POST | `/admin/categories` | `admin.categories.store` | `CategoryAdminController@store` | redirect |
| GET | `/admin/categories/{category}/edit` | `admin.categories.edit` | `CategoryAdminController@edit` | `Categories/Edit.vue` |
| PUT/PATCH | `/admin/categories/{category}` | `admin.categories.update` | `CategoryAdminController@update` | redirect |
| DELETE | `/admin/categories/{category}` | `admin.categories.destroy` | `CategoryAdminController@destroy` | redirect |
| GET | `/admin/colors` | `admin.colors.index` | `ColorAdminController@index` | `Colors/Index.vue` |
| GET | `/admin/colors/create` | `admin.colors.create` | `ColorAdminController@create` | `Colors/Create.vue` |
| POST | `/admin/colors` | `admin.colors.store` | `ColorAdminController@store` | redirect |
| GET | `/admin/colors/{color}/edit` | `admin.colors.edit` | `ColorAdminController@edit` | `Colors/Edit.vue` |
| PUT/PATCH | `/admin/colors/{color}` | `admin.colors.update` | `ColorAdminController@update` | redirect |
| DELETE | `/admin/colors/{color}` | `admin.colors.destroy` | `ColorAdminController@destroy` | redirect |
| GET | `/admin/products` | `admin.products.index` | `ProductAdminController@index` | `Products/Index.vue` |
| GET | `/admin/products/create` | `admin.products.create` | `ProductAdminController@create` | `Products/Create.vue` |
| POST | `/admin/products` | `admin.products.store` | `ProductAdminController@store` | redirect |
| GET | `/admin/products/{product}/edit` | `admin.products.edit` | `ProductAdminController@edit` | `Products/Edit.vue` |
| PUT/PATCH | `/admin/products/{product}` | `admin.products.update` | `ProductAdminController@update` | redirect |
| DELETE | `/admin/products/{product}` | `admin.products.destroy` | `ProductAdminController@destroy` | redirect |
| GET | `/admin/size-charts` | `admin.size-charts.index` | `SizeChartAdminController@index` | `SizeCharts/Index.vue` |
| GET | `/admin/size-charts/create` | `admin.size-charts.create` | `SizeChartAdminController@create` | `SizeCharts/Create.vue` |
| POST | `/admin/size-charts` | `admin.size-charts.store` | `SizeChartAdminController@store` | redirect |
| GET | `/admin/size-charts/{size_chart}/edit` | `admin.size-charts.edit` | `SizeChartAdminController@edit` | `SizeCharts/Edit.vue` |
| PUT/PATCH | `/admin/size-charts/{size_chart}` | `admin.size-charts.update` | `SizeChartAdminController@update` | redirect |
| DELETE | `/admin/size-charts/{size_chart}` | `admin.size-charts.destroy` | `SizeChartAdminController@destroy` | redirect |
| GET | `/admin/orders` | `admin.orders.index` | `OrderAdminController@index` | `Orders/Index.vue` |
| GET | `/admin/orders/{order}` | `admin.orders.show` | `OrderAdminController@show` | `Orders/Show.vue` |
| PATCH | `/admin/orders/{order}` | `admin.orders.update` | `OrderAdminController@update` | redirect → show |
| POST | `/admin/orders/{order}/shipment/book` | `admin.orders.shipment.book` | `OrderAdminController@book` | redirect → show |
| POST | `/admin/orders/{order}/shipment/sync-tracking` | `admin.orders.shipment.sync-tracking` | `OrderAdminController@syncTracking` | redirect → show |
| GET | `/admin/orders/{order}/shipments/{shipment}/postex/invoice` | `admin.orders.shipment.postex.invoice` | `OrderAdminController@postExInvoice` | inline PDF (no Inertia page) |
| GET | `/admin/orders/{order}/postex/load-sheet` | `admin.orders.postex.load-sheet` | `OrderAdminController@postExLoadSheet` | inline PDF |
| POST | `/admin/orders/{order}/shipments/{shipment}/postex/cancel` | `admin.orders.shipment.postex.cancel` | `OrderAdminController@postExCancel` | redirect → show |
| GET | `/admin/couriers` | `admin.couriers.index` | `CourierAdminController@index` | `Couriers/Index.vue` |
| GET | `/admin/coupons` | `admin.coupons.index` | `CouponAdminController@index` | `Coupons/Index.vue` |
| GET | `/admin/payment-settings` | `admin.payment-settings.edit` | `PaymentSettingsAdminController@edit` | `PaymentSettings/Index.vue` |
| PATCH | `/admin/payment-settings` | `admin.payment-settings.update` | `PaymentSettingsAdminController@update` | redirect |
| GET | `/admin/shipping-settings` | `admin.shipping-settings.edit` | `ShippingSettingsAdminController@edit` | `Shipping/Settings.vue` |
| PATCH | `/admin/shipping-settings` | `admin.shipping-settings.update` | `ShippingSettingsAdminController@update` | redirect |
| GET | `/admin/whatsapp-settings` | `admin.whatsapp-settings.edit` | `WhatsAppSettingsAdminController@edit` | `WhatsApp/Settings.vue` |
| PATCH | `/admin/whatsapp-settings` | `admin.whatsapp-settings.update` | `WhatsAppSettingsAdminController@update` | redirect |
| GET | `/admin/marketing-settings` | `admin.marketing-settings.edit` | `MarketingSettingsAdminController@edit` | `Marketing/Settings.vue` |
| PATCH | `/admin/marketing-settings` | `admin.marketing-settings.update` | `MarketingSettingsAdminController@update` | redirect |
| GET | `/admin/content-posts` | `admin.content-posts.index` | `ContentPostAdminController@index` | `Content/Index.vue` |
| GET | `/admin/content-posts/create` | `admin.content-posts.create` | `ContentPostAdminController@create` | `Content/Create.vue` |
| POST | `/admin/content-posts` | `admin.content-posts.store` | `ContentPostAdminController@store` | redirect |
| GET | `/admin/content-posts/{content_post}/edit` | `admin.content-posts.edit` | `ContentPostAdminController@edit` | `Content/Edit.vue` |
| PUT/PATCH | `/admin/content-posts/{content_post}` | `admin.content-posts.update` | `ContentPostAdminController@update` | redirect |
| DELETE | `/admin/content-posts/{content_post}` | `admin.content-posts.destroy` | `ContentPostAdminController@destroy` | redirect |
| GET | `/admin/products/export` | `admin.products.export` | `ProductAdminController@export` | CSV download |
| POST | `/admin/products/import` | `admin.products.import` | `ProductAdminController@import` | redirect |
| PATCH | `/admin/products/{product}/toggle-active` | `admin.products.toggle-active` | `ProductAdminController@toggleActive` | redirect |
| GET | `/admin/products/{product}/variants` | `admin.products.variants` | `ProductAdminController@variants` | JSON |
| GET | `/admin/orders/export` | `admin.orders.export` | `OrderAdminController@export` | CSV download |
| POST | `/admin/orders/bulk/book` | `admin.orders.bulk.book` | `OrderBulkAdminController@book` | redirect |
| POST | `/admin/orders/bulk/sync-tracking` | `admin.orders.bulk.sync-tracking` | `OrderBulkAdminController@syncTracking` | redirect |
| PATCH | `/admin/orders/bulk/update-status` | `admin.orders.bulk.update-status` | `OrderBulkAdminController@updateStatus` | redirect |
| PATCH | `/admin/orders/bulk/update-payment-status` | `admin.orders.bulk.update-payment-status` | `OrderBulkAdminController@updatePaymentStatus` | redirect |
| POST | `/admin/orders/bulk/print/labels` | `admin.orders.bulk.print-labels` | `OrderBulkAdminController@printLabels` | inline PDF |
| POST | `/admin/orders/bulk/print/packing-slips` | `admin.orders.bulk.print-packing-slips` | `OrderBulkAdminController@printPackingSlips` | inline PDF |
| POST | `/admin/orders/{order}/admin-discount` | `admin.orders.admin-discount.set` | `OrderAdjustmentsAdminController@setAdminDiscount` | redirect → show |
| POST | `/admin/orders/{order}/returns` | `admin.orders.returns.store` | `OrderReturnsAdminController@store` | redirect → show |
| GET | `/admin/returns` | `admin.returns.index` | `OrderReturnAdminController@index` | `Returns/Index.vue` |
| GET | `/admin/returns/{orderReturn}` | `admin.returns.show` | `OrderReturnAdminController@show` | redirect → orders.show |
| GET | `/admin/inventory/low-stock` | `admin.inventory.low-stock` | `LowStockAdminController@index` | `Inventory/LowStock.vue` |
| GET | `/admin/customers` | `admin.customers.index` | `CustomerAdminController@index` | `Customers/Index.vue` |
| GET | `/admin/finance/courier-settlements` | `admin.finance.courier-settlements` | `CourierSettlementAdminController@index` | `Finance/CourierSettlements.vue` |
| GET | `/admin/logistics/timeline` | `admin.logistics.timeline` | `LogisticsTimelineAdminController@index` | `Logistics/Timeline.vue` |
| GET | `/admin/notifications` | `admin.notifications.index` | `NotificationLogAdminController@index` | `Notifications/Index.vue` |
| POST | `/admin/notifications/{notificationLog}/retry` | `admin.notifications.retry` | `NotificationLogAdminController@retry` | redirect |
| GET | `/admin/abandoned-carts` | `admin.abandoned-carts.index` | `AbandonedCartAdminController@index` | `AbandonedCarts/Index.vue` |
| POST | `/admin/abandoned-carts/{cart}/whatsapp` | `admin.abandoned-carts.whatsapp.send` | `AbandonedCartAdminController@sendReminder` | redirect |
| POST | `/admin/abandoned-carts/whatsapp/bulk` | `admin.abandoned-carts.whatsapp.bulk` | `AbandonedCartAdminController@bulkSendReminder` | redirect |
| GET | `/admin/bargaining` | `admin.bargaining.index` | `BargainSessionAdminController@index` | `Bargaining/Index.vue` |
| GET | `/admin/bargaining/{bargain_session}` | `admin.bargaining.show` | `BargainSessionAdminController@show` | `Bargaining/Show.vue` |
| GET | `/admin/coupons/create` | `admin.coupons.create` | `CouponAdminController@create` | `Coupons/Create.vue` |
| POST | `/admin/coupons` | `admin.coupons.store` | `CouponAdminController@store` | redirect |
| GET | `/admin/storefront-assistant` | `admin.storefront-assistant.edit` | `StorefrontAssistantSettingsAdminController@edit` | `StorefrontAssistant/Settings.vue` |
| PATCH | `/admin/storefront-assistant` | `admin.storefront-assistant.update` | `StorefrontAssistantSettingsAdminController@update` | redirect |

> Phase 5 (audit follow-up) deletes the unbound `OrderShipmentController` and the orphan `Pages/Admin/_DesignSystem.vue`. Both are no longer present in the codebase.

### 1.2 Vue admin pages (`resources/js/Pages/Admin/**`, 28 files)

| File | Purpose |
| --- | --- |
| `Dashboard.vue` | KPI tiles for products / orders / brands / categories / colors / size charts / couriers / coupons. |
| `Products/Index.vue` | Paginated product list with brand. |
| `Products/Create.vue` | Wraps `ProductForm` for new product. |
| `Products/Edit.vue` | Wraps `ProductForm` with existing product payload. |
| `Products/ProductForm.vue` | Heavy form: brand/category/size‑chart selects, enums, SEO, video, variants/sizes, image uploads, features. |
| `Brands/Index.vue` | Paginated brand list with `products_count`. |
| `Brands/Create.vue` / `Edit.vue` | Brand create/edit form. |
| `Categories/Index.vue` | Paginated category list with parent + `products_count`. |
| `Categories/Create.vue` / `Edit.vue` | Category form (parent, slug, sort_order). |
| `Colors/Index.vue` | Paginated color list with hex preview. |
| `Colors/Create.vue` / `Edit.vue` | Color form (name, slug, hex). |
| `SizeCharts/Index.vue` | Paginated size charts with brand + row count. |
| `SizeCharts/Create.vue` / `Edit.vue` | Size‑chart builder (rows, gender/shoe enums). |
| `Orders/Index.vue` | Paginated orders with user. |
| `Orders/Show.vue` | Order detail: items, payments, shipments, couriers, status updates, PostEx invoice/load‑sheet/cancel actions. |
| `Couriers/Index.vue` | Paginated couriers list (read‑only). |
| `Coupons/Index.vue` | Paginated coupons list (read‑only). |
| `PaymentSettings/Index.vue` | Editable list of `PaymentMethodConfig` rows + COD fallback toggle. |
| `Shipping/Settings.vue` | Shipping settings + courier accounts grid + sender snapshot + dimensions. |
| `WhatsApp/Settings.vue` | WhatsApp toggles + admin recipients (textarea → array). |
| `Marketing/Settings.vue` | SEO meta + GA4 / Meta Pixel / TikTok Pixel + robots mode. |
| `Content/Index.vue` | Paginated content posts. |
| `Content/Create.vue` / `Edit.vue` | Article form (title, slug, body, excerpt, SEO, publish toggle/date). |

### 1.3 Shared components currently used by admin

A grep across `resources/js/Pages/Admin/**` for `@/Components/` returned **no matches**: admin pages today rely directly on Tailwind utility classes plus Inertia primitives. The only existing layout import is `AdminLayout` from `@/Layouts/AdminLayout.vue`.

Project‑level shared components that exist in `resources/js/Components/` (Breeze defaults — currently unused by admin, available for reuse if desired):

`ApplicationLogo.vue`, `Checkbox.vue`, `DangerButton.vue`, `Dropdown.vue`, `DropdownLink.vue`, `InputError.vue`, `InputLabel.vue`, `Modal.vue`, `NavLink.vue`, `PrimaryButton.vue`, `ResponsiveNavLink.vue`, `SecondaryButton.vue`, `TextInput.vue`.

Admin‑agnostic implication: we can introduce theme‑styled replacements under `Components/Admin/` without breaking storefront/Breeze pages.

---

## 2. Inventory of theme assets to port

All paths below are inside `vue-admin-design/`.

### 2.1 Blade files → Vue SFCs

| Blade source | Becomes |
| --- | --- |
| `resources/views/layouts/horizontalLayout.blade.php` (lines 32–85) | `resources/js/Layouts/AdminLayout.vue` (rewrite). |
| `resources/views/layouts/sections/navbar/navbar.blade.php` | `resources/js/Components/Admin/AdminNavbar.vue`. |
| `resources/views/layouts/sections/navbar/navbar-partial.blade.php` | Inlined into `AdminNavbar.vue` (brand + toggler + search + language + notifications + user dropdown). Trim demo-only items (style switcher, language list) — keep brand, toggler, search shell, user dropdown. |
| `resources/views/layouts/sections/menu/horizontalMenu.blade.php` (lines 6–56) | `resources/js/Components/Admin/AdminHorizontalMenu.vue` + `AdminMenuItem.vue`. |
| `resources/views/layouts/sections/menu/submenu.blade.php` (lines 5–52) | `resources/js/Components/Admin/AdminSubmenu.vue` (recursive). |
| `resources/views/layouts/sections/footer/footer.blade.php` (lines 8–28) | `resources/js/Components/Admin/AdminFooter.vue`. |
| `resources/views/_partials/macros.blade.php` (referenced from `navbar-partial.blade.php` line 10) | Inline SVG logo in `AdminNavbar.vue` (or keep `ApplicationLogo.vue`). |

> The `commonMaster.blade.php` chain is **not** ported. Inertia uses `resources/views/app.blade.php` as the only Blade root; theme `<head>` wiring (fonts, vendor CSS, vendor JS bundles) becomes Vite imports inside the admin chunk (see 2.2/2.3).

### 2.2 SCSS bundles + import points

Source SCSS lives in `resources/assets/vendor/scss/`:

```1:5:vue-admin-design/resources/assets/vendor/scss/core.scss
@import "bootstrap";
@import "colors";
@import "bootstrap-extended";
@import "components";
@import "custom-styles";
```

Files to vendor in (copy into `resources/css/admin/vendor/`):
- `_bootstrap.scss`, `_bootstrap-extended.scss`, `_colors.scss`, `_components.scss`, `_custom-styles.scss`, `core.scss`.
- The whole `_components/` and `_bootstrap-extended/` partial trees referenced via SCSS imports — copy as a unit (do not selectively prune; partials cross‑reference each other).
- A `theme-default.scss` (skin) — derive from `vendor-admin-design/resources/assets/vendor/scss/_bootstrap-extended/_skin.scss` defaults so light/dark CSS variables resolve.

**Compilation strategy:** `npm i -D sass` then `@import "@/css/admin/vendor/core.scss";` from a new `resources/css/admin.scss`. Tailwind v4 (already installed via `@tailwindcss/vite`) stays in the storefront entry only — Tailwind utilities are **not** imported into the admin SCSS to keep the two design systems isolated.

**Import point:** introduce `resources/js/admin.js` (admin‑only Vite entry) that imports `resources/css/admin.scss` and the vendor JS listed below, then mounts the same Inertia app. Update `resources/views/app.blade.php` so admin pages get the admin entry (gate via `$page['component']` starting with `Admin/`). Storefront pages keep `resources/js/app.js` + Tailwind only — no SCSS bleed.

> Decision required (see §9): single entry with conditional CSS import vs. two Vite inputs. Plan recommends two inputs.

### 2.3 Vendor JS the layout needs

Found in `resources/assets/vendor/js/`:

| Asset | Use | Init in Vue |
| --- | --- | --- |
| `bootstrap.js` (Bootstrap 5 bundle) | Dropdowns, collapses, modals, offcanvas, tooltips. | `import 'bootstrap'` once in `admin.js`. |
| `helpers.js` | Layout helpers (`Helpers.setCollapsed`, etc.) used by `main.js`. | Side‑effect import; expose `window.Helpers`. |
| `menu.js` (Menu class) | Powers horizontal menu hover/click + mobile slide‑in. | Instantiate inside `AdminLayout.vue` `onMounted`, dispose in `onBeforeUnmount`. Re‑instantiate on Inertia `router.on('navigate')` if DOM is replaced. |
| `dropdown-hover.js` | Hover‑open dropdowns in horizontal menu. | Same lifecycle as `menu.js`. |
| `mega-dropdown.js` | Optional mega‑menu support. | Defer until/if a mega menu is added. |
| `template-customizer.js` | Demo customizer panel. | **Skip** — not shipped to production. |

Theme `resources/assets/js/main.js` (line 341 references `data-template^='horizontal-menu'`): port the small bootstrapping it does for Menu + layout toggles into a single `resources/js/admin/initLayout.js`; do not import `main.js` wholesale because it pulls in demo‑page glue.

Page-specific scripts to port selectively:
- `dashboards-analytics.js` → optional ApexCharts wrappers in `Dashboard.vue`.
- `app-ecommerce-product-add.js` → drag/drop image uploads, tagify (port into `ProductForm.vue` only if needed).
- `app-ecommerce-product-list.js` / `app-ecommerce-category-list.js` / `app-ecommerce-order-details.js` / `app-ecommerce-settings.js` — read for column patterns; do **not** import; we re-implement the same UX with Vue + server pagination.

### 2.4 Static assets

Theme `public/assets/` does **not** ship a prebuilt `core.css` / `theme-default.css` in this repo (no matches under `public/assets/vendor/css/**/*.css` or `public/assets/css/**/*.css`); we build CSS from SCSS sources in 2.2.

To copy into the main app:

| From `vue-admin-design/public/assets/` | To main app | Notes |
| --- | --- | --- |
| `img/illustrations/*` | `public/admin-assets/img/illustrations/` | Empty‑state and dashboard illustrations referenced by ported pages. |
| `img/avatars/*` | `public/admin-assets/img/avatars/` | Default user avatars in navbar dropdown. |
| `img/icons/brands/*` | `public/admin-assets/img/icons/brands/` | Payment / SSO brand icons used by settings pages. |
| `vendor/fonts/tabler-icons/*` (and any font files referenced by SCSS via `$tabler-icons-font-path`) | `public/admin-assets/fonts/tabler-icons/` | Tabler icon font is the icon system used across the layout (`<i class="ti tabler-...">`). Configure SCSS variable to point here. |
| `vendor/fonts/iconify/*` (if referenced) | same scheme | Only if SCSS resolves it. |

Image references inside ported Vue components should use `/admin-assets/...` absolute paths or `import` statements through Vite for hashing. Do **not** mix theme assets into Tailwind storefront paths.

---

## 3. Target architecture

### 3.1 Layout (`resources/js/Layouts/AdminLayout.vue`)

- Root element classes: `layout-wrapper layout-navbar-full layout-horizontal layout-without-menu` (matches lines 33–34 of `vue-admin-design/resources/views/layouts/horizontalLayout.blade.php`).
- Children, in order, mirroring the Blade structure:
  1. `<AdminNavbar />` (full‑width because `$navbarFull = true`).
  2. `<div class="layout-page"><div class="content-wrapper">`
     - `<AdminHorizontalMenu />` (renders `AdminMenuItem` + recursive `AdminSubmenu`).
     - `<div :class="containerClass">` — `containerClass` = `container-fluid` by default, `container-xxl` when `contentLayout==='compact'`. With prop `flex` true, switch to `container-fluid d-flex align-items-stretch flex-grow-1 p-0` and add `flex-grow-1 container-p-y` otherwise.
     - `<slot />` (page content).
     - `<AdminFooter />` (toggle via prop).
     - `<div class="content-backdrop fade" />`
  3. `<div class="layout-overlay layout-menu-toggle" />` (mobile click‑outside).
  4. `<div class="drag-target" />` (mobile slide‑in target).
- Props: `:flex`, `:hideMenu`, `:hideFooter`, `:contentLayout` ('default' | 'compact'), `:title` (optional page header text used by `AdminPageHeader`).
- Lifecycle:
  - `onMounted`: `new Menu(document.querySelector('#layout-menu'))` (from vendor `menu.js`); init Bootstrap tooltips/popovers; wire `[data-bs-toggle="layout"]` togglers (mobile menu).
  - `onBeforeUnmount`: dispose Menu + Bootstrap instances.
  - Listen to Inertia `router.on('navigate')` to re‑init when the layout instance persists across page changes (Inertia layout persistence pattern).
- A11y: `<aside id="layout-menu">` retains role `navigation` with `aria-label="Admin"`; mobile toggler buttons must be `<button>` (not `<a>` placeholders from theme).

### 3.2 New components under `resources/js/Components/Admin/`

| Component | Responsibility |
| --- | --- |
| `AdminNavbar.vue` | Brand, mobile menu toggle, search input shell, language/notifications dropdowns (placeholders), user dropdown wrapper. |
| `AdminUserDropdown.vue` | Logged‑in user info + logout (`route('logout')`) + profile (`route('profile.edit')`). |
| `AdminHorizontalMenu.vue` | `<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0">` + `<ul class="menu-inner">`; loops over `menu` array. |
| `AdminMenuItem.vue` | Top‑level item; renders `<Link>` or button‑like toggle when it has children; applies `.active` based on `route().current(menu.slug)` or `route().current(menu.activeSlug)`. |
| `AdminSubmenu.vue` | Recursive submenu (`<ul class="menu-sub">`) — supports nested children identical to `submenu.blade.php`. |
| `AdminFooter.vue` | Copyright + version + optional links. |
| `AdminBreadcrumb.vue` | `nav[aria-label="breadcrumb"] > ol.breadcrumb`; takes `:items=[{label, href?}]`. |
| `AdminPageHeader.vue` | Page title + subtitle + breadcrumb slot + actions slot (right). |
| `DataTable.vue` | Wrapper around `<table class="table table-hover">` with toolbar, search input, server‑side pagination footer (Inertia partial reload). Slots for header cells, row cells, empty state. |
| `DataTablePagination.vue` | Renders Laravel paginator links via Inertia `<Link>` preserving `?page` and other filters. |
| `StatCard.vue` | Title + value + delta + icon + footer link (Dashboard tiles). |
| `FormSection.vue` | `<div class="card mb-6">` with header + body slot + actions slot — used by every Create/Edit page. |
| `FormField.vue` | Wraps label + input + error; reuses Inertia `useForm` errors. |
| `EmptyState.vue` | Icon + heading + description + primary CTA. |
| `StatusBadge.vue` | Colored pill: maps status string (`paid`, `pending`, `booked`, etc.) → Bootstrap badge class via a small lookup. |
| `ConfirmDialog.vue` | Bootstrap modal wrapper for delete confirmations. |
| `Toast.vue` + `useFlash.js` | Listens to Inertia `page.props.flash` (`status`, `success`, `error`) and shows a Bootstrap toast. |

### 3.3 Menu data source — `resources/js/admin/menu.js`

Single source of truth, no JSON import. Sketch:

Real shape implemented in `resources/js/admin/menu.js` (after Phase 5 audit follow-ups):

```js
export function buildAdminMenu(route) {
  return [
    { label: 'Dashboard', icon: 'ti tabler-smart-home', href: route('admin.dashboard'), active: 'admin.dashboard' },
    {
      label: 'Catalog', icon: 'ti tabler-package',
      children: [
        { label: 'Products', href: route('admin.products.index'), active: 'admin.products.*' },
        { label: 'Low / out of stock', href: route('admin.inventory.low-stock'), active: 'admin.inventory.*' },
        { label: 'Brands', href: route('admin.brands.index'), active: 'admin.brands.*' },
        { label: 'Categories', href: route('admin.categories.index'), active: 'admin.categories.*' },
        { label: 'Colors', href: route('admin.colors.index'), active: 'admin.colors.*' },
        { label: 'Size charts', href: route('admin.size-charts.index'), active: 'admin.size-charts.*' },
      ],
    },
    {
      label: 'Sales', icon: 'ti tabler-shopping-cart',
      children: [
        { label: 'Orders', href: route('admin.orders.index'), active: 'admin.orders.*' },
        { label: 'Customers', href: route('admin.customers.index'), active: 'admin.customers.*' },
        { label: 'Returns', href: route('admin.returns.index'), active: 'admin.returns.*' },
        { label: 'Abandoned carts', href: route('admin.abandoned-carts.index'), active: 'admin.abandoned-carts.*' },
        { label: 'Bargaining', href: route('admin.bargaining.index'), active: 'admin.bargaining.*' },
        { label: 'Coupons', href: route('admin.coupons.index'), active: 'admin.coupons.*' },
      ],
    },
    {
      label: 'Logistics', icon: 'ti tabler-truck',
      children: [
        { label: 'Couriers', href: route('admin.couriers.index'), active: 'admin.couriers.*' },
        { label: 'Shipment timeline', href: route('admin.logistics.timeline'), active: 'admin.logistics.*' },
        { label: 'Shipping settings', href: route('admin.shipping-settings.edit'), active: 'admin.shipping-settings.*' },
      ],
    },
    {
      label: 'Operations', icon: 'ti tabler-bell',
      children: [
        { label: 'Notifications', href: route('admin.notifications.index'), active: 'admin.notifications.*' },
        { label: 'Courier settlements', href: route('admin.finance.courier-settlements'), active: 'admin.finance.*' },
      ],
    },
    {
      label: 'Settings', icon: 'ti tabler-settings',
      children: [
        { label: 'Payments', href: route('admin.payment-settings.edit'), active: 'admin.payment-settings.*' },
        { label: 'WhatsApp', href: route('admin.whatsapp-settings.edit'), active: 'admin.whatsapp-settings.*' },
        { label: 'Storefront Assistant', href: route('admin.storefront-assistant.edit'), active: 'admin.storefront-assistant.*' },
        { label: 'Marketing & SEO', href: route('admin.marketing-settings.edit'), active: 'admin.marketing-settings.*' },
      ],
    },
    { label: 'Journal', icon: 'ti tabler-notebook', href: route('admin.content-posts.index'), active: 'admin.content-posts.*' },
    { label: 'Storefront', icon: 'ti tabler-external-link', href: route('store.home'), target: '_blank' },
  ];
}
```

Active state: `route().current(item.active)` (Ziggy supports glob `'admin.products.*'`).

### 3.4 Asset strategy — admin‑only Vite chunk

- Add `resources/js/admin.js` and `resources/css/admin.scss`.
- Update `vite.config.js` to declare two inputs: `resources/js/app.js` (storefront) and `resources/js/admin.js` (admin).
- `resources/js/admin.js` imports `bootstrap`, `helpers.js`, `menu.js`, `dropdown-hover.js`, then `resources/css/admin.scss`. It does **not** import `app.css` (Tailwind).
- `resources/views/app.blade.php` selects the entry: when `$page['component']` starts with `Admin/`, emit `@vite(['resources/js/admin.js', "resources/js/Pages/{$page['component']}.vue"])`; otherwise keep `resources/js/app.js` as today. This keeps the storefront Tailwind bundle untouched.
- Inertia createApp is shared: factor `resources/js/inertia/createInertia.js` and call from both entries (`app.js` and `admin.js`).

---

## 4. Page‑by‑page redesign matrix

Theme reference paths are relative to `vue-admin-design/resources/views/`. Props are inferred from the corresponding `App\Http\Controllers\Web\Admin\*` files cited in §1.

| Page | Theme reference | Components to reuse | Key UI elements | Controller props (verified) | Risks / edge cases |
| --- | --- | --- | --- | --- | --- |
| `Pages/Admin/Dashboard.vue` | `content/dashboard/dashboards-analytics.blade.php`, `content/apps/app-ecommerce-dashboard.blade.php` | `StatCard`, `AdminPageHeader`, optional `apexcharts/vue3` | 8 KPI cards (products, orders, brands, categories, colors, size_charts, couriers, coupons); recent orders mini‑table; quick‑links grid. | `counts: { products, orders, brands, categories, colors, size_charts, couriers, coupons }`. | If charts are added, gate ApexCharts behind dynamic import to avoid bundle bloat. |
| `Pages/Admin/Products/Index.vue` | `content/apps/app-ecommerce-product-list.blade.php` | `DataTable`, `StatusBadge`, `EmptyState`, `ConfirmDialog` | Search, brand filter, status filter; columns: image / name + slug / brand / variants count / status / created / actions (Edit, Delete). | `products`: paginator of `Product` with `brand`. | Enable server‑side filters by sending `?search=`, `?brand_id=`, `?status=` — currently the controller only accepts page; document it as a follow‑up if filters added in UI. |
| `Pages/Admin/Products/Create.vue` | `content/apps/app-ecommerce-product-add.blade.php` | `ProductForm`, `FormSection` | Wraps `ProductForm` in card + breadcrumb. | `brands, categories, colors, size_charts, enums { fit_guidance, gender, shoe_type }`. | None beyond ProductForm. |
| `Pages/Admin/Products/Edit.vue` | same as Create | `ProductForm`, `FormSection` | Same as Create + delete button (DELETE `admin.products.destroy`). | Same + `product`. | Image deletion: existing form already supports this — preserve. |
| `Pages/Admin/Products/ProductForm.vue` | `app-ecommerce-product-add.blade.php` (left/right column split) | `FormSection`, `FormField`, native repeater UI for variants/sizes | Tabs or sections: Basics, Media (images, video poster, video URL), Descriptions, SEO, Pricing+Variants (per‑color rows with size matrix), Inventory, Visibility. | Same shape as Create + `product` (id, slug, description, meta, video, fit_guidance, gender, shoe_type, fit_notes, size_info, features[], is_active, images[], variants[].sizes[]). | Heavy form — keep `useForm` on the parent component to centralize errors; preserve existing field names exactly so `StoreProductRequest` / `UpdateProductRequest` validate unchanged. |
| `Pages/Admin/Brands/Index.vue` | `content/apps/app-ecommerce-customer-all.blade.php` (table pattern) | `DataTable`, `StatusBadge` | Columns: name, slug, products count, created, actions. | `brands`: paginator with `products_count`. | Delete is blocked when `products_count > 0` — UI must show why (controller returns `withErrors`). |
| `Pages/Admin/Brands/Create.vue` / `Edit.vue` | `app-ecommerce-product-add.blade.php` (organize section) | `FormSection`, `FormField` | Fields: name, slug, description, logo (if model supports). | Edit: `brand`. | `StoreBrandRequest` / `UpdateBrandRequest` rules drive validation messages. |
| `Pages/Admin/Categories/Index.vue` | `content/apps/app-ecommerce-category-list.blade.php` | `DataTable`, `StatusBadge` | Columns: name, slug, parent, products count, sort_order, actions. | `categories`: paginator with `parent` + `products_count`. | Delete blocked when has products or children — show banner from `errors.category`. |
| `Pages/Admin/Categories/Create.vue` / `Edit.vue` | `app-ecommerce-category-list.blade.php` offcanvas form | `FormSection`, `FormField` | Fields: name, slug, parent_id (select with `parents`), sort_order, description. | Create: `parents`. Edit: `category, parents` (excluding self). | None. |
| `Pages/Admin/Colors/Index.vue` | `content/forms/forms-extras.blade.php` (color picker pattern) | `DataTable` | Columns: hex swatch, name, slug, created, actions. | `colors` paginator. | Delete blocked when used by variants. |
| `Pages/Admin/Colors/Create.vue` / `Edit.vue` | same | `FormSection`, `FormField` | Fields: name, slug, hex (color input). | Edit: `color`. | Validate hex pattern client‑side. |
| `Pages/Admin/SizeCharts/Index.vue` | `content/apps/app-ecommerce-product-list.blade.php` | `DataTable` | Columns: brand, name, gender, shoe_type, rows count, actions. | `charts`: paginator with `brand` + `rows_count`. | Delete blocked when linked to a product. |
| `Pages/Admin/SizeCharts/Create.vue` / `Edit.vue` | `content/tables/tables-basic.blade.php` (rows table pattern) | `FormSection`, `FormField`, dynamic rows table | Brand select, name, gender, shoe_type, repeater for rows (sort_order, label, uk_size, eu_size, pk_size, foot_cm). | Create: `brands, enums`. Edit: + `chart` (with `rows`). | Row reordering — implement via simple up/down buttons (no SortableJS dependency). |
| `Pages/Admin/Orders/Index.vue` | `content/apps/app-ecommerce-order-list.blade.php` | `DataTable`, `StatusBadge` | Columns: order #, customer, total, payment status, order status, created, actions. | `orders` paginator with `user`. | Add filters as a follow‑up (controller currently returns full latest list). |
| `Pages/Admin/Orders/Show.vue` | `content/apps/app-ecommerce-order-details.blade.php` | `FormSection`, `StatusBadge`, `ConfirmDialog`, `useForm` | Two columns: left = items table + payments + shipments timeline; right = customer card, addresses, status update form, courier assignment form, "Book shipment", "Sync tracking", PostEx invoice/load‑sheet/cancel actions. | `order, order_statuses, payment_statuses, couriers, defaultBookingCourierId`. | PostEx endpoints return PDFs → open in a new tab (`<a target="_blank">`). The cancel action uses POST → handle via `<Link method="post" as="button">`. |
| `Pages/Admin/Couriers/Index.vue` | `content/apps/app-ecommerce-customer-all.blade.php` | `DataTable`, `StatusBadge` | Columns: code, name, adapter, sort_order, active, actions. | `couriers` paginator. | Read‑only; no create/update/destroy routes today. |
| `Pages/Admin/Coupons/Index.vue` | similar list | `DataTable`, `StatusBadge` | Columns: code, type, value, expires, status. | `coupons` paginator. | Has a `Create coupon` action wired to `admin.coupons.create` / `admin.coupons.store`; deletion / update routes are still TODO. |
| `Pages/Admin/Coupons/Create.vue` | `app-ecommerce-product-add.blade.php` (single-column form) | `FormSection`, `FormField` | Code, type, value, scope, max_uses, expires_at, is_active. | none (POST `admin.coupons.store`). | Validate code uniqueness server-side via `StoreCouponRequest`. |
| `Pages/Admin/PaymentSettings/Index.vue` | `content/apps/app-ecommerce-settings-payments.blade.php` | `FormSection`, `FormField`, repeater table | List of payment methods (id, gateway_code, customer_label, fee_fixed, fee_percent, sort_order, enabled toggle); global "Fallback online → COD" switch. | `methods, fallback_online_failed_to_cod`. | Submit one form covering the whole array; mirror existing `UpdatePaymentSettingsRequest` shape exactly. |
| `Pages/Admin/Shipping/Settings.vue` | `content/apps/app-ecommerce-settings-shipping.blade.php` | `FormSection`, `FormField`, repeater | Default courier select, courier_assignment_default, auto‑book toggles, tracking interval, sender snapshot fields, PostEx pickup/store codes, default dimensions; Courier Accounts grid with credentials inputs (api_token write‑only). | `settings, couriers_for_select, courier_accounts_form`. | Credentials are write‑only — don't preload, only update if non‑empty. Validate that token never leaks back to client. |
| `Pages/Admin/WhatsApp/Settings.vue` | `content/apps/app-ecommerce-settings-notifications.blade.php` | `FormSection`, `FormField` | Toggles + textarea for admin recipients (one per line) → array on submit. | `settings: { enabled_customer_notifications, enabled_admin_notifications, admin_recipients_text }`. | Need to convert textarea to array client‑side before submit (split on `\n`, trim, filter). |
| `Pages/Admin/Marketing/Settings.vue` | `content/apps/app-ecommerce-settings-details.blade.php` + `content/apps/app-ecommerce-settings-checkout.blade.php` | `FormSection`, `FormField` | SEO meta (home title/description, OG image), GA4 / Meta / TikTok pixel toggles + IDs, robots mode select + custom robots.txt textarea. | `settings: { home_meta_title, ..., robots_mode, robots_custom }`. | Robots custom textarea only relevant for `robots_mode='custom'`. |
| `Pages/Admin/Content/Index.vue` | `content/apps/app-ecommerce-manage-reviews.blade.php` | `DataTable`, `StatusBadge` | Columns: title, slug, published, published_at, actions. | `posts` paginator. | None. |
| `Pages/Admin/Content/Create.vue` / `Edit.vue` | `content/apps/app-ecommerce-product-add.blade.php` (single‑column with side card) | `FormSection`, `FormField` | Title, slug, excerpt, body (textarea, future Tiptap/Quill), pillar_keyword, meta title/description, is_published toggle, published_at_local datetime‑local. | Create: none. Edit: `post`. | `published_at_local` is a string in `Y-m-d\TH:i` (server already formatted) — bind as `<input type="datetime-local">`. |

---

## 5. Cross‑cutting concerns

- **Theming.** Default to **light** with optional dark via `data-bs-theme="dark"` on `<html>`. Primary brand color: TBD (see §9). Font: keep theme default (Public Sans / system) loaded via SCSS; do not pull Figtree from `app.blade.php` for admin pages — load admin font via SCSS or `@vite` admin chunk only.
- **Forms.** Continue Inertia `useForm`; replace ad‑hoc Tailwind `<input>` markup with `FormField` (Bootstrap `form-control`). Validation errors render below each input via `<div class="invalid-feedback">` and `is-invalid` toggling, mirroring theme alerts.
- **File uploads.** Products and (future) brand logos / content posts use `useForm({...}).post(url, { forceFormData: true })`. Drag‑and‑drop area styled like `forms-file-upload.js` but implemented in pure Vue (no Dropzone dependency).
- **Tables.** Server‑side pagination via Inertia partial reloads. `DataTable` accepts `:paginator` (Laravel paginator JSON), `:columns`, slot rows. Filters/search use `router.get(route, params, { preserveState: true, replace: true })`.
- **Flash & toasts.** Inertia `usePage().props.flash` already populated by controllers (`status`, `success`, `error`). `useFlash.js` watches it and pushes Bootstrap toasts via a single `<Toast>` component mounted in `AdminLayout.vue`.
- **Permissions / middleware.** `routes/admin.php` already protects with `auth + admin`; **no** changes to `EnsureUserIsAdmin` middleware. The redesign adds no new auth surfaces.
- **Accessibility.**
  - Keyboard nav: `<a class="menu-link">` placeholders converted to `<button>` when they only toggle; `aria-expanded`, `aria-haspopup="menu"` on dropdown triggers; arrow‑key navigation handled by Bootstrap dropdown JS.
  - Focus rings: keep theme `:focus-visible` styles; do not strip outline.
  - Live region for toast container: `role="status" aria-live="polite"`.
- **Responsiveness.** Below `xl`, navbar collapses and horizontal menu becomes off‑canvas; toggler in `navbar-partial.blade.php` (line 26–30) maps to `<button class="layout-menu-toggle">`. `.layout-overlay` + `.drag-target` + `Menu` from `vendor/js/menu.js` drive open/close.
- **i18n.** Strings stay English. Centralize labels in `resources/js/admin/strings.js` (or pass through controllers for server‑sourced strings) so a future i18n pass needs only one substitution layer. Do not introduce vue‑i18n now.

---

## 6. Implementation phases

### Phase 0 — Asset wiring + shell preview

- **Deliverables**
  - `resources/js/admin.js`, `resources/css/admin.scss`, vendor SCSS copied to `resources/css/admin/vendor/`, vendor JS imported.
  - `vite.config.js` updated with two inputs.
  - `resources/views/app.blade.php` chooses entry based on `$page['component']` prefix.
  - Stub `AdminLayout.vue` rendering wrapper classes + empty navbar/menu/footer; existing pages still render.
  - Temporary `?admin_preview=1` query flag (or env‑gated route `/admin/_preview`) showing the empty shell with a placeholder grid so the design can be reviewed before content is migrated.
- **Files touched**: `vite.config.js`, `resources/views/app.blade.php`, `resources/js/admin.js`, `resources/css/admin.scss`, `resources/css/admin/vendor/**`, `resources/js/Layouts/AdminLayout.vue` (replaced behind a flag).
- **Manual test checklist**
  - [ ] `npm run build` passes; admin chunk emits.
  - [ ] `php artisan route:list --name=admin` is unchanged.
  - [ ] Storefront pages (Home, Shop, Cart, Product) render visually identically (no Bootstrap leak).
  - [ ] `/admin` loads empty shell with horizontal menu placeholder; mobile toggler opens slide‑in.
  - [ ] `tests/Feature/Admin/AdminRoutesTest.php` still passes.
- **Rollback**: revert two‑input change in `vite.config.js` and the conditional in `app.blade.php`. (The old `AdminLayoutLegacy.vue` file was retired in Phase 5 — restore from git history if a roll-back is ever needed.)

### Phase 1 — Navigation

- **Deliverables**: `AdminNavbar.vue`, `AdminUserDropdown.vue`, `AdminHorizontalMenu.vue`, `AdminMenuItem.vue`, `AdminSubmenu.vue`, `AdminFooter.vue`, `resources/js/admin/menu.js`, `useFlash.js`, `Toast.vue`.
- **Files touched**: `resources/js/Components/Admin/**`, `resources/js/admin/**`, `AdminLayout.vue`.
- **Manual test checklist**
  - [ ] Every `admin.*` named route reachable from menu.
  - [ ] Active state highlights match current route (test by visiting each top‑level area).
  - [ ] Logout link calls `route('logout')` POST and redirects.
  - [ ] Toast appears for flash `status` and `error` after redirect.
- **Rollback**: the feature-flagged Legacy nav was deleted in Phase 5; revert via git history if needed.

### Phase 2 — Reusable building blocks

- **Deliverables**: `DataTable.vue`, `DataTablePagination.vue`, `FormSection.vue`, `FormField.vue`, `StatCard.vue`, `AdminPageHeader.vue`, `AdminBreadcrumb.vue`, `EmptyState.vue`, `StatusBadge.vue`, `ConfirmDialog.vue`. Phase 5 added `BulkSummaryModal.vue` and the `useFocusTrap` composable.
- **Files touched**: `resources/js/Components/Admin/**`. (The originally proposed `Pages/Admin/_DesignSystem.vue` preview page was never wired to a route and was deleted in Phase 5.)
- **Manual test checklist**
  - [ ] Each component renders with sample props on the preview page.
  - [ ] `DataTable` supports loading, empty, error states.
  - [ ] `FormField` shows `useForm` errors correctly.
- **Rollback**: components are additive; deletion has no downstream impact until pages adopt them.

### Phase 3 — Page redesigns (in dependency order)

Each step ships a single PR that only touches the listed Vue page(s). Verified after each PR with the route's manual smoke test.

- [ ] `Dashboard.vue` (KPIs use `StatCard`, recent orders use `DataTable`).
- [ ] Catalog block:
  - [ ] `Products/Index.vue`
  - [ ] `Products/Create.vue` + `Products/Edit.vue` + `ProductForm.vue` (largest piece; consider splitting into Basics/Media/SEO/Pricing/Variants sub‑PRs).
  - [ ] `Categories/Index.vue` → `Create.vue` → `Edit.vue`.
  - [ ] `Brands/Index.vue` → `Create.vue` → `Edit.vue`.
  - [ ] `Colors/Index.vue` → `Create.vue` → `Edit.vue`.
  - [ ] `SizeCharts/Index.vue` → `Create.vue` → `Edit.vue`.
- [ ] Sales:
  - [ ] `Orders/Index.vue`
  - [ ] `Orders/Show.vue` (largest; split actions panel into a sub‑PR if needed).
  - [ ] `Coupons/Index.vue`.
- [ ] Logistics:
  - [ ] `Couriers/Index.vue`.
  - [ ] `Shipping/Settings.vue`.
- [ ] Settings:
  - [ ] `PaymentSettings/Index.vue`.
  - [ ] `WhatsApp/Settings.vue`.
  - [ ] `Marketing/Settings.vue`.
- [ ] Journal:
  - [ ] `Content/Index.vue` → `Create.vue` → `Edit.vue`.
- **Manual test checklist (per page)**
  - [ ] Page loads with admin user, returns 200.
  - [ ] Inertia props consumed without console errors.
  - [ ] Forms submit and round‑trip validation.
  - [ ] Pagination + filters preserve query string.
  - [ ] Storefront unaffected (`/`, `/shop`, `/cart`).
- **Rollback**: revert the page file; controller and routes unchanged so rollback is one‑file per step.

### Phase 4 — Polish

- **Deliverables**: dark mode toggle persisted in localStorage, refined empty/loading states (`EmptyState`), keyboard navigation audit, performance budget (admin chunk gzipped < 350 KB initial; pages < 60 KB each), Lighthouse a11y ≥ 90 on Dashboard / Products / Orders Show.
- **Files touched**: `resources/css/admin.scss` tweaks, components only.
- **Rollback**: pure visual; no controller dependencies.

---

## 7. Risk register

- **CSS conflicts.** Bootstrap resets and Tailwind utilities can collide if both load on the same page. Mitigation: separate Vite entries, never import `app.css` into admin chunk. Verify by loading `/` after each phase.
- **Bootstrap JS vs. existing libs.** Project does not currently load Bootstrap (only `@inertiajs/vue3` + Vue). Adding Bootstrap is admin‑scoped and side‑effect free for storefront. Risk: jQuery is **not** required — Bootstrap 5 bundle is vanilla.
- **Bundle size.** Bootstrap (~70 KB gzipped) + theme SCSS (~80 KB gzipped) + Tabler icons font (~50 KB). Mitigation: code‑split admin entry; lazy‑load ApexCharts via dynamic import in Dashboard only.
- **Inertia partial reload + theme JS.** `Menu` and Bootstrap dropdowns mutate DOM imperatively; on Inertia navigation the `AdminLayout` instance persists but Bootstrap drops references on element re‑render. Mitigation: re‑initialize via `router.on('navigate')` and dispose on `before` event; expose stable IDs for menu nodes.
- **`tests/Feature/Admin/AdminRoutesTest.php` regressions.** The test exercises `admin.dashboard` and `admin.products.create` 200 responses with an admin user. As long as Inertia component names (`Admin/Dashboard`, `Admin/Products/Create`) and route names stay the same, the test continues to pass. Risk only if Vite manifest is malformed for the admin chunk → guard with a smoke test that visits the dashboard in a browser after build.
- **Image / asset paths.** Inline `<img src="/assets/img/...">` from theme references will 404 unless copied to `public/admin-assets/`. Mitigation: copy assets in Phase 0; lint Vue files for `/assets/` prefix and rewrite to `/admin-assets/`.
- **Tabler font 404.** SCSS variable `$tabler-icons-font-path` must point to copied location; otherwise icons render as boxes. Mitigation: set the variable in `resources/css/admin.scss` before importing `core.scss`.
- **PostEx PDF endpoints.** `Orders/Show.vue` redesign must keep `<a>` (not Inertia `<Link>`) for `admin.orders.shipment.postex.invoice` and `admin.orders.postex.load-sheet` because they return PDFs not Inertia responses.
- **Form payload shape regressions.** Each redesigned form must keep the exact payload structure consumed by the matching `App\Http\Requests\Admin\*Request` rule sets. Mitigation: pre‑port snapshot the existing `useForm({...})` definition and reuse.

---

## 8. Acceptance criteria

- All `admin.*` routes render under the new layout (verified by visiting each route with an admin user).
- `php artisan route:list --name=admin` output is byte‑identical before and after.
- `php artisan test --filter=Admin` is green; no new failures repo‑wide.
- `npm run build` succeeds and emits both `app` and `admin` entry chunks.
- Lighthouse a11y ≥ 90 on `Admin/Dashboard`, `Admin/Products/Index`, `Admin/Orders/Show`.
- Storefront pages are visually unchanged (compare before/after screenshots of `/`, `/shop`, `/product/{slug}`, `/cart`).
- Bundle: admin entry initial load ≤ 350 KB gzipped, page chunks ≤ 60 KB each.
- Dark mode toggle persists across reloads (localStorage key `admin.theme`).

---

## 9. Open questions for the user

- **Theme defaults**: light or dark by default? Brand primary color (hex)? Logo asset to swap into `AdminNavbar.vue`?
- **Tailwind in admin**: keep existing Tailwind utility classes on admin pages (mixed Bootstrap + Tailwind) or fully migrate admin pages to theme classes? Plan currently assumes **fully migrate** to keep CSS scoped.
- **Vite entries**: confirm two‑input split (`resources/js/app.js` + `resources/js/admin.js`) is acceptable. Alternative: single entry with conditional CSS import (simpler but heavier storefront bundle).
- **Charts on Dashboard**: include ApexCharts widgets (revenue trend, orders sparkline) now or as a Phase 4 add‑on?
- **Customizer panel**: do we want the theme's runtime customizer (skin / direction / layout switcher) shipped, or omit it entirely? Plan currently **omits** it.
- ~~**Legacy `OrderShipmentController`**~~ — resolved in Phase 5: file deleted.

---

## Next step

Start Phase 0: paste this prompt into Cursor — *“Execute Phase 0 of `docs/admin-redesign-plan.md`: add admin Vite entry + SCSS, copy theme assets, scaffold empty `AdminLayout.vue` shell, and gate it behind `?admin_preview=1`; do not touch any `Pages/Admin/*.vue` yet.”*

---

## Phase 5 — Audit follow-ups (delivered)

This phase closed the gaps in the post-Phase-4 admin audit (security, ops visibility, missing screens, automation). Tracked in `cursor/plans/admin-audit-followups_*.plan.md`.

### Security & data integrity (P0)

- **Bulk payment guardrails** — split `admin.orders.bulk.update-status` into status-only and a new `admin.orders.bulk.update-payment-status`. The new `BulkUpdatePaymentStatusRequest` requires an `override` checkbox + non-empty `reason` for destructive transitions (`paid`, `refunded`, `canceled`); both flow into `PaymentStatusHistory.meta` and `OrderAuditEvent.meta` for audit. UI now opens a dedicated modal with `confirmDanger` summary (selected count + first 5 order numbers).
- **Run Courier credentials** — `ShippingSettingsAdminController@edit` no longer echoes `client_code`, `profile_id`, `api_vendor`. Replaced with `has_*` booleans + masked password inputs and "Configured / Not set" badges.
- **Order-detail dangerous actions** — `bookOrder`, `saveAdminDiscount` (with current → projected total preview) and `submitReturn` are wrapped in `confirmDanger` with summaries.
- **Orphans removed** — `app/Http/Controllers/Web/Admin/OrderShipmentController.php` and `resources/js/Pages/Admin/_DesignSystem.vue` deleted.

### Ops visibility (P1)

- **Payments ledger card** on `Orders/Show.vue` rendering `order.payments` (gateway, status, amount, paid_at, external_id, expandable meta JSON).
- **Order list filters & presets** — `OrderAdminController::index` now accepts `date_from`, `date_to`, `courier_id`, `delivery_status`, `payment_gateway`, `preset` (`today`, `today_unbooked`, `pending_payment_24h`, `booking_failed`). Vue exposes new selects + chip presets.
- **bulk_summary surfacing** — extended `useFlash.js` to detect `flash.bulk_summary` and open a globally-mounted `BulkSummaryModal` (in `AdminLayout.vue`) listing skipped rows with per-order links.
- **Notification logs** — new `NotificationLogAdminController` (index + WhatsApp retry), page `Pages/Admin/Notifications/Index.vue` with filters / payload drawer / "Failed only" + "WhatsApp DLQ" presets, and a new **Operations** menu group.
- **Orders CSV export** — `admin.orders.export` reuses the same query builder as `index()`; the formerly-disabled Export button now downloads the current filtered view.
- **Legacy layout** — `?admin_legacy=1` branch and `AdminLayoutLegacy.vue` removed from `AdminLayout.vue` so navigation is single-sourced from `buildAdminMenu`.

### New screens & automation (F + C)

- **Returns dashboard** — `OrderReturnAdminController` + `Pages/Admin/Returns/Index.vue` with KPI tiles, restock filter, date range. Detail link redirects to the order page hash.
- **Low-stock work queue** — `LowStockAdminController` paginates `VariantSize` rows on two tabs (`low`, `out`); page is `Pages/Admin/Inventory/LowStock.vue` and the Dashboard "Low stock SKUs" / "Out of stock" tiles deep-link to the right tab.
- **Customer directory** — `CustomerAdminController` exposes `users` with subquery-derived `orders_count`, `lifetime_spend`, `last_order_at`. Page `Pages/Admin/Customers/Index.vue` supports search + segment chips (`Has orders`, `No orders yet`, `VIP`).
- **Courier COD settlement** — `App\Domain\Finance\CourierSettlementService` aggregates per-courier outstanding vs. settled COD and discrepancies. `CourierSettlementAdminController` + `Pages/Admin/Finance/CourierSettlements.vue` show the per-courier table and an outstanding-orders drill-down.
- **Global shipment timeline** — `LogisticsTimelineAdminController` over `ShipmentEvent` with courier/status/date filters. Page `Pages/Admin/Logistics/Timeline.vue`.
- **Abandoned cart WhatsApp** — `AbandonedCartAdminController::sendReminder` and `bulkSendReminder` dispatch via the new `App\Domain\Marketing\AbandonedCartReminderService`, log to `notification_logs`, and the page now exposes per-row + bulk "Send WhatsApp" buttons (disabled when no phone is on file). Skipped rows bubble through `bulk_summary`.
- **Background reconciliation** — three new jobs (`ReconcileFailedBookingsJob`, `ProcessNotificationRetryJob`, `ReconcileCodFromCourierWebhookJob`) registered in `routes/console.php` (every 15m / 10m / hourly). The COD job removes the need for the bulk-Paid override path locked down above.

### Polish (a11y + dashboard + docs)

- **Dashboard "today" tiles** — `AdminDashboardMetrics::kpis` adds `orders_today`, `cod_collected_today`, `cod_pending_today`, `bookings_failed_today`. The new tile row in `Dashboard.vue` deep-links to the matching Orders presets.
- **Modal a11y** — added `useFocusTrap` composable (`resources/js/admin/useFocusTrap.js`) and `aria-labelledby` on every bulk modal (`Orders/Index.vue`) and the return modal (`Orders/Show.vue`). Esc closes, Tab cycles, focus restored on close.
- **Docs sync** — this section, the §1 route table additions, the §3.3 menu sketch refresh, and the Coupons row update.

### Out of scope (still TODO)

- Coupons update / destroy controllers.
- Detailed `Pages/Admin/Returns/Show.vue` (currently redirects to the parent order; explicit page can be added later).
- Lighthouse a11y verification ≥ 90 on the new screens (audit only; no measurement automation in this phase).
