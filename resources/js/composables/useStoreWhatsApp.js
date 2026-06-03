import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

function formatMoneyPlain(n) {
    const num = Number(n) || 0;
    return `Rs ${num.toLocaleString('en-PK', { maximumFractionDigits: 0 })}`;
}

function formatPlacedAt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * @param {string} baseUrl wa.me link without text param
 * @param {string} [text]
 */
export function whatsAppUrlWithText(baseUrl, text) {
    if (!baseUrl || baseUrl === '#') return '#';
    try {
        const url = new URL(baseUrl);
        if (text) {
            url.searchParams.set('text', text);
        }
        return url.toString();
    } catch {
        return baseUrl;
    }
}

/**
 * @param {object} order
 * @param {{ includeItems?: boolean }} [opts]
 */
export function buildOrderSupportMessage(order, opts = {}) {
    if (!order?.order_number) return 'Hi, I need help with my order.';
    const lines = [
        `Hi, I need help with my order ${order.order_number}.`,
        `Status: ${order.status ?? '—'}`,
        `Payment: ${order.payment_status ?? '—'}`,
        `Total: ${formatMoneyPlain(order.grand_total)}`,
    ];
    if (order.placed_at) {
        lines.push(`Placed: ${formatPlacedAt(order.placed_at)}`);
    }
    if (opts.includeItems && Array.isArray(order.items) && order.items.length) {
        const preview = order.items
            .slice(0, 3)
            .map((item) => {
                const parts = [item.product_name];
                if (item.size_label) parts.push(`Size ${item.size_label}`);
                if (item.quantity > 1) parts.push(`×${item.quantity}`);
                return `• ${parts.join(' · ')}`;
            })
            .join('\n');
        lines.push('', 'Items:', preview);
        if (order.items.length > 3) {
            lines.push(`• +${order.items.length - 3} more`);
        }
    } else if (order.items_preview) {
        lines.push(`Items: ${order.items_preview}`);
    }
    return lines.join('\n');
}

export function useStoreWhatsApp() {
    const page = usePage();
    const baseUrl = computed(() => page.props.storefront?.support_whatsapp_url ?? '#');

    function supportUrl(text) {
        return whatsAppUrlWithText(baseUrl.value, text);
    }

    function orderSupportUrl(order, opts) {
        return supportUrl(buildOrderSupportMessage(order, opts));
    }

    return { baseUrl, supportUrl, orderSupportUrl, buildOrderSupportMessage };
}
