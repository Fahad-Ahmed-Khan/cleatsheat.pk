<?php

return [
    'enabled' => filter_var(env('BARGAIN_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // How long a bargaining session is valid (minutes).
    'session_ttl_minutes' => (int) env('BARGAIN_SESSION_TTL_MINUTES', 30),

    // How long an accepted/locked price is valid (minutes).
    'lock_ttl_minutes' => (int) env('BARGAIN_LOCK_TTL_MINUTES', 60),

    // Deterministic counter-offer tuning.
    'counter' => [
        // Minimum concession step (PKR). (Keeps negotiation moving.)
        'min_step' => (int) env('BARGAIN_MIN_STEP_PKR', 100),
        'concession' => [
            'enabled' => filter_var(env('BARGAIN_COUNTER_CONCESSION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'round_to' => (int) env('BARGAIN_COUNTER_CONCESSION_ROUND_TO', 50),
            'min_step_pkr' => (int) env('BARGAIN_COUNTER_CONCESSION_MIN_STEP_PKR', 25),
            'max_step_fraction_of_gap' => (float) env('BARGAIN_COUNTER_CONCESSION_MAX_FRACTION', 0.35),
            'near_floor_gap_threshold_pkr' => (int) env('BARGAIN_COUNTER_CONCESSION_NEAR_FLOOR_GAP', 400),
            // Seeded jitter multiplier range, e.g. 0.18 means ±18%.
            'randomness' => (float) env('BARGAIN_COUNTER_CONCESSION_RANDOMNESS', 0.18),
        ],
    ],

    /**
     * When a customer's stated price is already within policy, nudge slightly toward list
     * before locking (seller-friendly; skipped when nudge would not increase price).
     */
    'in_range_nudge' => [
        'enabled' => filter_var(env('BARGAIN_IN_RANGE_NUDGE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'min_step_pkr' => (int) env('BARGAIN_IN_RANGE_NUDGE_MIN_STEP_PKR', 25),
        'max_fraction_of_gap' => (float) env('BARGAIN_IN_RANGE_NUDGE_MAX_FRACTION', 0.4),
    ],

    'intent' => [
        'accept_min_confidence' => (float) env('BARGAIN_INTENT_ACCEPT_MIN_CONFIDENCE', 0.72),
        'offer_typo_min_confidence' => (float) env('BARGAIN_INTENT_OFFER_TYPO_MIN_CONFIDENCE', 0.82),
    ],

    /**
     * Session-scoped customer integrity floor (never settle below a passed-on customer amount).
     * Strategic relaxation is off by default — enabling weakens the hard floor; tune carefully.
     */
    'integrity' => [
        'allow_strategic_floor_relaxation' => filter_var(env('BARGAIN_INTEGRITY_ALLOW_STRATEGIC_RELAX', false), FILTER_VALIDATE_BOOLEAN),
        'strategic_relax_min_pkr' => env('BARGAIN_INTEGRITY_STRATEGIC_RELAX_MIN_PKR', '500.00'),
        'strategic_relax_min_negotiation_turns' => (int) env('BARGAIN_INTEGRITY_STRATEGIC_RELAX_MIN_TURNS', 4),
    ],

    /** Near-policy-floor behaviour: resistance score + hold-firm / plateau in engine. */
    'resistance' => [
        'hold_firm_score_threshold' => (int) env('BARGAIN_RESISTANCE_HOLD_FIRM_SCORE', 85),
        'hold_firm_min_same_offer_streak' => (int) env('BARGAIN_RESISTANCE_HOLD_FIRM_STREAK', 2),
        'concession_count_curve_weight' => (float) env('BARGAIN_RESISTANCE_CONCESSION_CURVE_WEIGHT', 0.05),
        'max_step_frac_resistance_scale' => (float) env('BARGAIN_RESISTANCE_MAX_STEP_FRAC_SCALE', 0.55),
    ],

    'stubborn' => [
        'same_offer_streak' => (int) env('BARGAIN_STUBBORN_SAME_OFFER_STREAK', 3),
        'min_concessions_without_customer_up' => (int) env('BARGAIN_STUBBORN_MIN_CONCESSIONS', 5),
    ],

    /** Defend-line: tiny numeric gap + phrase heuristic (no price move when already plateaued). */
    'defend' => [
        'max_gap_pkr' => (float) env('BARGAIN_DEFEND_MAX_GAP_PKR', 250.0),
    ],

    /** Optional cooldown between shop-line decreases (0 = disabled). */
    'concession_cooldown_minutes' => (int) env('BARGAIN_CONCESSION_COOLDOWN_MINUTES', 0),

    // Optional AI (LLM) for natural language only (pricing stays deterministic).
    'ai' => [
        // filter_var correctly parses "true"/"false" from .env (raw env() strings break bool casts).
        'enabled' => filter_var(env('BARGAIN_AI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string) env('BARGAIN_AI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        // Prefer BARGAIN_AI_API_KEY; fallback to OPENAI_API_KEY for convenience.
        'api_key' => trim((string) (env('BARGAIN_AI_API_KEY') ?: env('OPENAI_API_KEY') ?: '')),
        'model' => env('BARGAIN_AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('BARGAIN_AI_TEMPERATURE', 0.75),
        'max_tokens' => (int) env('BARGAIN_AI_MAX_TOKENS', 180),
        // Set false on Windows/Laragon if OpenSSL cannot verify (dev only; not for production).
        'http_verify' => filter_var(env('BARGAIN_AI_HTTP_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'context' => [
            'max_messages' => (int) env('BARGAIN_AI_CONTEXT_MAX_MESSAGES', 6),
            'max_chars_per_message' => (int) env('BARGAIN_AI_CONTEXT_MAX_CHARS_PER_MESSAGE', 300),
            'max_total_chars' => (int) env('BARGAIN_AI_CONTEXT_MAX_TOTAL_CHARS', 1800),
        ],
        'analyzer' => [
            'max_messages' => (int) env('BARGAIN_AI_ANALYZER_MAX_MESSAGES', 50),
        ],
    ],
];
