/** File upload keys on storefront settings — omit unless a real File was chosen. */
export const STOREFRONT_UPLOAD_KEYS = [
    'logo',
    'logo_dark',
    'favicon',
    'hero_image',
    'promo_banner_image',
    'default_og_image',
];

/**
 * Build PATCH payload for multipart storefront save (PHP cannot parse PATCH + files).
 *
 * @param {Record<string, unknown>} data
 * @returns {Record<string, unknown>}
 */
export function storefrontSettingsPayload(data) {
    const payload = { ...data, _method: 'patch' };

    for (const key of STOREFRONT_UPLOAD_KEYS) {
        if (!(payload[key] instanceof File)) {
            delete payload[key];
        }
    }

    return payload;
}
