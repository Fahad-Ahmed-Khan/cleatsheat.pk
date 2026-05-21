import { usePage } from '@inertiajs/vue3';

let pixelsBootstrapped = false;

function bootstrapPixels(marketing) {
    if (!marketing || pixelsBootstrapped) {
        return;
    }

    if (marketing.gtm_enabled && marketing.gtm_container_id) {
        const gtmId = marketing.gtm_container_id;
        window.dataLayer = window.dataLayer || [];
        const gtmScript = document.createElement('script');
        gtmScript.textContent = `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer',${JSON.stringify(gtmId)});`;
        document.head.appendChild(gtmScript);
        const noscript = document.createElement('noscript');
        const iframe = document.createElement('iframe');
        iframe.src = `https://www.googletagmanager.com/ns.html?id=${encodeURIComponent(gtmId)}`;
        iframe.height = 0;
        iframe.width = 0;
        iframe.style.display = 'none';
        iframe.style.visibility = 'hidden';
        noscript.appendChild(iframe);
        document.body.insertBefore(noscript, document.body.firstChild);
    }

    if (marketing.ga4_enabled && marketing.ga4_measurement_id && !marketing.gtm_enabled) {
        const id = marketing.ga4_measurement_id;
        const s1 = document.createElement('script');
        s1.async = true;
        s1.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
        document.head.appendChild(s1);
        const s2 = document.createElement('script');
        s2.textContent = `
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', ${JSON.stringify(id)}, { send_page_view: false });
`;
        document.head.appendChild(s2);
    }

    if (marketing.meta_pixel_enabled && marketing.meta_pixel_id) {
        const pid = marketing.meta_pixel_id;
        const s = document.createElement('script');
        s.textContent = `
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', ${JSON.stringify(pid)});
fbq('track', 'PageView');
`;
        document.head.appendChild(s);
        const nos = document.createElement('noscript');
        const img = document.createElement('img');
        img.height = 1;
        img.width = 1;
        img.style.display = 'none';
        img.src = `https://www.facebook.com/tr?id=${encodeURIComponent(pid)}&ev=PageView&noscript=1`;
        nos.appendChild(img);
        document.body.appendChild(nos);
    }

    if (marketing.tiktok_pixel_enabled && marketing.tiktok_pixel_id) {
        const tid = marketing.tiktok_pixel_id;
        const s = document.createElement('script');
        s.textContent = `
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load(${JSON.stringify(tid)});
  ttq.page();
}(window, document, 'ttq');
`;
        document.head.appendChild(s);
    }

    pixelsBootstrapped = true;
}

function gtagEvent(name, params) {
    if (typeof window.gtag === 'function') {
        window.gtag('event', name, params || {});
    }
}

export function useStoreAnalytics() {
    const page = usePage();

    function marketing() {
        return page.props.marketing ?? {};
    }

    function ensure() {
        bootstrapPixels(marketing());
    }

    function ga4ItemsFromLine(line) {
        const pid = line?.product?.id != null ? String(line.product.id) : undefined;
        const name = line?.product?.name ?? 'Item';
        return [
            {
                item_id: pid,
                item_name: name,
                item_variant: line?.variant?.sku ?? undefined,
                price: line?.unit_price,
                quantity: line?.quantity ?? 1,
            },
        ];
    }

    function trackPageView() {
        ensure();
        const m = marketing();
        if (m.ga4_enabled && m.ga4_measurement_id && typeof window.gtag === 'function') {
            window.gtag('event', 'page_view', {
                page_path: window.location.pathname + window.location.search,
                page_title: document.title,
            });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'PageView');
        }
    }

    function trackViewItem(payload) {
        ensure();
        const m = marketing();
        const items = [
            {
                item_id: String(payload.productId),
                item_name: payload.name,
                item_category: payload.category,
                price: payload.price,
                quantity: 1,
            },
        ];
        if (m.ga4_enabled) {
            gtagEvent('view_item', {
                currency: 'PKR',
                value: payload.price,
                items,
            });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'ViewContent', {
                content_ids: [String(payload.productId)],
                content_type: 'product',
                value: payload.price,
                currency: 'PKR',
            });
        }
        if (m.tiktok_pixel_enabled && typeof window.ttq !== 'undefined') {
            window.ttq.track('ViewContent', {
                content_id: String(payload.productId),
                content_type: 'product',
                value: payload.price,
                currency: 'PKR',
            });
        }
    }

    function trackAddToCart(payload) {
        ensure();
        const m = marketing();
        const items = [
            {
                item_id: String(payload.productId),
                item_name: payload.name,
                price: payload.price,
                quantity: payload.quantity ?? 1,
            },
        ];
        if (m.ga4_enabled) {
            gtagEvent('add_to_cart', {
                currency: 'PKR',
                value: payload.price * (payload.quantity ?? 1),
                items,
            });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'AddToCart', {
                content_ids: [String(payload.productId)],
                content_type: 'product',
                value: payload.price * (payload.quantity ?? 1),
                currency: 'PKR',
            });
        }
        if (m.tiktok_pixel_enabled && typeof window.ttq !== 'undefined') {
            window.ttq.track('AddToCart', {
                content_id: String(payload.productId),
                content_type: 'product',
                value: payload.price * (payload.quantity ?? 1),
                currency: 'PKR',
            });
        }
    }

    function trackBeginCheckout(checkout) {
        ensure();
        const m = marketing();
        const value = checkout?.value ?? 0;
        const items = checkout?.items ?? [];
        if (m.ga4_enabled) {
            gtagEvent('begin_checkout', {
                currency: 'PKR',
                value,
                items,
            });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'InitiateCheckout', { value, currency: 'PKR', content_ids: items.map((i) => i.item_id) });
        }
        if (m.tiktok_pixel_enabled && typeof window.ttq !== 'undefined') {
            window.ttq.track('InitiateCheckout', { value, currency: 'PKR', contents: items.map((i) => ({ content_id: i.item_id, quantity: i.quantity })) });
        }
    }

    function trackPurchase(order) {
        ensure();
        const m = marketing();
        const value = order?.grand_total ?? 0;
        const items =
            order?.items?.map((i) => ({
                item_id: i.product_id != null ? String(i.product_id) : String(i.product_variant_id),
                item_name: i.product_name,
                price: (i.line_total ?? 0) / Math.max(1, i.quantity ?? 1),
                quantity: i.quantity ?? 1,
            })) ?? [];
        if (m.ga4_enabled) {
            gtagEvent('purchase', {
                transaction_id: order?.order_number,
                currency: 'PKR',
                value,
                items,
            });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'Purchase', { value, currency: 'PKR', content_ids: items.map((x) => x.item_id) });
        }
        if (m.tiktok_pixel_enabled && typeof window.ttq !== 'undefined') {
            window.ttq.track('CompletePayment', { value, currency: 'PKR', contents: items.map((x) => ({ content_id: x.item_id, quantity: x.quantity })) });
        }
    }

    function trackBargainAccepted(payload) {
        ensure();
        const m = marketing();
        const value = payload?.value ?? 0;
        if (m.ga4_enabled) {
            gtagEvent('bargain_accepted', { currency: 'PKR', value, items: payload?.items });
        }
        if (m.meta_pixel_enabled && typeof window.fbq === 'function') {
            window.fbq('track', 'CustomEvent', { event: 'BargainAccepted', value, currency: 'PKR' });
        }
        if (m.tiktok_pixel_enabled && typeof window.ttq !== 'undefined') {
            window.ttq.track('SubmitForm', { value, currency: 'PKR' });
        }
    }

    return {
        ensure,
        trackPageView,
        trackViewItem,
        trackAddToCart,
        trackBeginCheckout,
        trackPurchase,
        trackBargainAccepted,
    };
}
