import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                // Agri theme, driven by CSS variables in resources/css/app.css
                leaf: {
                    DEFAULT: 'rgb(var(--leaf-rgb) / <alpha-value>)',
                    dark: 'rgb(var(--leaf-dark-rgb) / <alpha-value>)',
                    light: 'rgb(var(--leaf-light-rgb) / <alpha-value>)',
                },
                surface: 'rgb(var(--surface-rgb) / <alpha-value>)',
                sage: {
                    DEFAULT: 'rgb(var(--sage-rgb) / <alpha-value>)',
                    dark: 'rgb(var(--sage-dark-rgb) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--accent-rgb) / <alpha-value>)',
                    dark: 'rgb(var(--accent-dark-rgb) / <alpha-value>)',
                },
                ink: {
                    DEFAULT: 'rgb(var(--ink-rgb) / <alpha-value>)',
                    soft: 'rgb(var(--ink-soft-rgb) / <alpha-value>)',
                },
            },
            fontFamily: {
                sans: ['"Source Sans 3"', '"Noto Sans Devanagari"', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', '"Noto Serif Devanagari"', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
