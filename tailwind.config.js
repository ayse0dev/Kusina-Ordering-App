import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    safelist: ['reveal', 'is-visible', 'is-above', 'is-scrolled', 'scroll-progress'],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Lato', ...defaultTheme.fontFamily.sans],
                display: ['"Josefin Sans"', ...defaultTheme.fontFamily.sans],
                script: ['"Great Vibes"', 'cursive'],
            },
            colors: {
                navy: {
                    50: '#f6f5f2', 100: '#ebe9e4', 200: '#d6d3cb', 300: '#b3afa4', 400: '#8a867b',
                    500: '#615e55', 600: '#403e38', 700: '#2c2b27', 800: '#1d1c1a', 900: '#121211', 950: '#0a0a09',
                },
                sun: {
                    50: '#faf6ea', 100: '#f3ead2', 200: '#e8d7ae', 300: '#d9bd82', 400: '#c9a45c',
                    500: '#b8924a', 600: '#9a7430', 700: '#7d5c24', 800: '#5f4519', 900: '#46330f',
                },
                brand: {
                    50: '#faf6ec', 100: '#f3e9d2', 200: '#e8d3a5', 300: '#d9b97a', 400: '#c9a45c',
                    500: '#b08a45', 600: '#9a7430', 700: '#7d5c24', 800: '#5f4519', 900: '#46330f',
                },
                cream: { DEFAULT: '#fbfaf7', dark: '#f1eee7', line: '#e4dfd2' },
            },
            keyframes: {
                float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-9px)' } },
                rise: { '0%': { opacity: '0', transform: 'translateY(26px)' }, '100%': { opacity: '1', transform: 'none' } },
                fade: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                kenburns: { '0%': { transform: 'scale(1)' }, '100%': { transform: 'scale(1.14)' } },
                spinSlow: { to: { transform: 'rotate(360deg)' } },
                glow: {
                    '0%, 100%': { filter: 'drop-shadow(0 0 2px rgb(201 164 92 / 0.35))' },
                    '50%': { filter: 'drop-shadow(0 0 11px rgb(244 214 134 / 0.9))' },
                },
                shineText: { '0%': { backgroundPosition: '220% 0' }, '100%': { backgroundPosition: '-120% 0' } },
                pulseRing: { '0%': { transform: 'scale(1)', opacity: '0.55' }, '100%': { transform: 'scale(1.55)', opacity: '0' } },
                ringSweep: { '0%, 60%': { transform: 'translateX(-140%) skewX(-20deg)' }, '100%': { transform: 'translateX(260%) skewX(-20deg)' } },
            },
            animation: {
                float: 'float 6s ease-in-out infinite',
                rise: 'rise 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) both',
                fade: 'fade 0.7s ease-out both',
                kenburns: 'kenburns 26s ease-in-out infinite alternate',
                'spin-slow': 'spinSlow 48s linear infinite',
                glow: 'glow 3.4s ease-in-out infinite',
                'shine-text': 'shineText 5.5s linear infinite',
                'pulse-ring': 'pulseRing 3.2s ease-out infinite',
                'ring-sweep': 'ringSweep 5s ease-in-out infinite',
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
