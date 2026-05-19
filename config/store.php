<?php

return [
    'hero_title' => env('STORE_HERO_TITLE', 'Walk with confidence'),
    'hero_subtitle' => env(
        'STORE_HERO_SUBTITLE',
        'Premium footwear sized for Pakistan — UK, EU & PK guides, fast delivery, effortless checkout.'
    ),
    'hero_badge' => env('STORE_HERO_BADGE', 'New season'),

    /** Optional full-width hero background image URL (home page). */
    'hero_image_url' => env('STORE_HERO_IMAGE_URL'),

    'shipping_flat' => env('STORE_SHIPPING_FLAT', 200),
    'cod_fee' => env('STORE_COD_FEE', 0),

    /** E.164 style including country code, no spaces e.g. +923001234567 */
    'support_phone' => env('STORE_SUPPORT_PHONE', '+923001234567'),

    /** WhatsApp chat URL — defaults to wa.me with support_phone digits */
    'support_whatsapp_url' => env('STORE_SUPPORT_WHATSAPP_URL'),

    'delivery_days_min' => (int) env('STORE_DELIVERY_DAYS_MIN', 2),
    'delivery_days_max' => (int) env('STORE_DELIVERY_DAYS_MAX', 5),

    'return_policy_summary' => env(
        'STORE_RETURN_POLICY_SUMMARY',
        '14-day returns on unworn shoes in original packaging. COD orders: inspect at delivery.'
    ),

    /** Short text printed on shipping labels (required / handling notes). */
    'shipping_label_required_notes' => env(
        'STORE_SHIPPING_LABEL_NOTES',
        'Inspect parcel before accepting. For damage or wrong items, note on the POD and contact us within 24 hours. Keep original packaging for returns.'
    ),

    /**
     * When true, admin discounts cannot be changed after the order is delivered.
     */
    'admin_discount_lock_after_delivered' => env('STORE_ADMIN_DISCOUNT_LOCK_AFTER_DELIVERED', true),
];
