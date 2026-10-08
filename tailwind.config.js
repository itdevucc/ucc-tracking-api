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
            colors: {
                red: { 50: '#fdf2f3', 100: '#fbe5e7', 200: '#f4bcc2', 400: '#d85360', 500: '#c92a38', 600: '#b8101e', 700: '#960d18', 800: '#780e17', 900: '#5b1016' },
                gray: { 50: '#f5f7f8', 100: '#edf1f3', 200: '#dce3e7', 300: '#bdc9cf', 400: '#8b9ea8', 500: '#5b707b', 600: '#4d606a', 700: '#3e4e57', 800: '#2c3940', 900: '#1c272d' },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
