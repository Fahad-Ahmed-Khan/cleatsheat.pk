import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                stadium: 'rgb(var(--stadium-bg) / <alpha-value>)',
                'stadium-dim': 'rgb(var(--stadium-dim) / <alpha-value>)',
                'stadium-muted': 'rgb(var(--stadium-muted) / <alpha-value>)',
                'stadium-container': 'rgb(var(--stadium-container) / <alpha-value>)',
                'stadium-container-high': 'rgb(var(--stadium-container-high) / <alpha-value>)',
                'stadium-white': 'rgb(var(--stadium-white) / <alpha-value>)',
                'stadium-ink': 'rgb(var(--stadium-ink) / <alpha-value>)',
                'stadium-ink-variant': 'rgb(var(--stadium-ink-variant) / <alpha-value>)',
                'stadium-lime': '#dfff00',
                'stadium-lime-ink': '#191e00',
                'stadium-lime-muted': '#647400',
                'stadium-olive': '#576500',
                'stadium-outline': 'rgb(var(--stadium-outline) / <alpha-value>)',
                'stadium-outline-soft': 'rgb(var(--stadium-outline-soft) / <alpha-value>)',
                'stadium-inverse': 'rgb(var(--stadium-inverse) / <alpha-value>)',
                'stadium-inverse-text': 'rgb(var(--stadium-inverse-text) / <alpha-value>)',
                'stadium-secondary': 'rgb(var(--stadium-secondary) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                store: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Sora', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                stadium: '0 10px 30px -10px rgba(26, 28, 28, 0.08)',
                'stadium-lg': '0 8px 32px 0 rgba(0, 0, 0, 0.08)',
                'stadium-nav': '0 -4px 20px 0 rgba(0, 0, 0, 0.05)',
            },
            maxWidth: {
                content: '1200px',
            },
        },
    },

    plugins: [forms],
};
