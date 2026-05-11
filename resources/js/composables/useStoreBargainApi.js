const BASE = '/api/v1/bargain';

/**
 * JSON bargain API (same-origin session cookie; no CSRF on api routes).
 *
 * @param {string} path
 * @param {RequestInit} [options]
 * @returns {Promise<any>}
 */
export async function bargainApiFetch(path, options = {}) {
    const res = await fetch(`${BASE}${path}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...options.headers,
        },
        ...options,
    });

    let body = {};
    try {
        body = await res.json();
    } catch {
        body = {};
    }

    if (!res.ok || body.success === false) {
        const msg = body.message || `Request failed (${res.status})`;
        const err = new Error(msg);
        err.code = body.code;
        err.status = res.status;
        throw err;
    }

    return body;
}

export function useStoreBargainApi() {
    return {
        /**
         * @param {{ product_variant_id: number, customer_name?: string, customer_phone: string, guest_token?: string }} payload
         */
        startSession(payload) {
            return bargainApiFetch('/sessions', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },

        /**
         * @param {number} sessionId
         * @param {{ customer_phone: string, message: string }} payload
         */
        sendMessage(sessionId, payload) {
            return bargainApiFetch(`/sessions/${sessionId}/messages`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },

        /**
         * @param {number} sessionId
         * @param {{ customer_phone: string, price?: number|string|null }} payload
         */
        accept(sessionId, payload) {
            return bargainApiFetch(`/sessions/${sessionId}/accept`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },

        /**
         * @param {number} sessionId
         * @param {{ customer_phone: string }} payload
         */
        decline(sessionId, payload) {
            return bargainApiFetch(`/sessions/${sessionId}/decline`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },

        /**
         * @param {number} sessionId
         * @param {string} customerPhone
         */
        getStatus(sessionId, customerPhone) {
            const q = new URLSearchParams({ customer_phone: customerPhone });
            return bargainApiFetch(`/sessions/${sessionId}?${q.toString()}`, { method: 'GET' });
        },
    };
}
