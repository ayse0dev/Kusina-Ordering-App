import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Lato', ...defaultTheme.fontFamily.sans],
                display: ['"Josefin Sans"', ...defaultTheme.fontFamily.sans], // headings & navigation
                script: ['"Great Vibes"', 'cursive'],                          // elegant accent lines
            },
            // Luxury dark palette: charcoal-black + antique gold. (Keeps the existing
            // `navy` / `sun` / `brand` class names, so every view picks the new look up.)
            colors: {
                navy: { // charcoal / black
                    50: '#f6f5f2', 100: '#ebe9e4', 200: '#d6d3cb', 300: '#b3afa4', 400: '#8a867b',
                    500: '#615e55', 600: '#403e38', 700: '#2c2b27', 800: '#1d1c1a', 900: '#121211', 950: '#0a0a09',
                },
                sun: { // antique gold
                    50: '#faf6ea', 100: '#f3ead2', 200: '#e8d7ae', 300: '#d9bd82', 400: '#c9a45c',
                    500: '#b8924a', 600: '#9a7430', 700: '#7d5c24', 800: '#5f4519', 900: '#46330f',
                },
                brand: { // bronze: primary actions (white text stays readable)
                    50: '#faf6ec', 100: '#f3e9d2', 200: '#e8d3a5', 300: '#d9b97a', 400: '#c9a45c',
                    500: '#b08a45', 600: '#9a7430', 700: '#7d5c24', 800: '#5f4519', 900: '#46330f',
                },
                cream: { DEFAULT: '#fbfaf7', dark: '#f1eee7', line: '#e4dfd2' },
            },
            borderRadius: { md: '2px', lg: '2px', xl: '3px', '2xl': '4px', '3xl': '6px' },
            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / 0.04), 0 2px 12px -4px rgb(0 0 0 / 0.08)',
                lift: '0 2px 4px rgb(0 0 0 / 0.06), 0 16px 32px -10px rgb(0 0 0 / 0.28)',
            },
        },
    },

    plugins: [forms],
};
