export function buildAdminMenu(route) {
    return [
        {
            label: 'Dashboard',
            icon: 'ti tabler-smart-home',
            href: route('admin.dashboard'),
            active: 'admin.dashboard',
        },
        {
            label: 'Catalog',
            icon: 'ti tabler-package',
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
            label: 'Sales',
            icon: 'ti tabler-shopping-cart',
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
            label: 'Logistics',
            icon: 'ti tabler-truck',
            children: [
                { label: 'Couriers', href: route('admin.couriers.index'), active: 'admin.couriers.*' },
                { label: 'Shipment timeline', href: route('admin.logistics.timeline'), active: 'admin.logistics.*' },
                { label: 'Shipping settings', href: route('admin.shipping-settings.edit'), active: 'admin.shipping-settings.*' },
            ],
        },
        {
            label: 'Operations',
            icon: 'ti tabler-bell',
            children: [
                { label: 'Notifications', href: route('admin.notifications.index'), active: 'admin.notifications.*' },
                { label: 'Courier settlements', href: route('admin.finance.courier-settlements'), active: 'admin.finance.*' },
            ],
        },
        {
            label: 'Settings',
            icon: 'ti tabler-settings',
            children: [
                { label: 'Payments', href: route('admin.payment-settings.edit'), active: 'admin.payment-settings.*' },
                { label: 'WhatsApp', href: route('admin.whatsapp-settings.edit'), active: 'admin.whatsapp-settings.*' },
                { label: 'Storefront Assistant', href: route('admin.storefront-assistant.edit'), active: 'admin.storefront-assistant.*' },
                { label: 'Marketing & SEO', href: route('admin.marketing-settings.edit'), active: 'admin.marketing-settings.*' },
            ],
        },
        {
            label: 'Journal',
            icon: 'ti tabler-notebook',
            href: route('admin.content-posts.index'),
            active: 'admin.content-posts.*',
        },
        {
            label: 'Storefront',
            icon: 'ti tabler-external-link',
            href: route('store.home'),
            target: '_blank',
        },
    ];
}

