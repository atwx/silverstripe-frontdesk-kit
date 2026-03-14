/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './templates/**/*.ss',
        './client/src/**/*.{js,css}',
    ],
    plugins: [
        require('daisyui'),
        require('@tailwindcss/forms'),
    ],
    daisyui: {
        themes: ['light', 'dark'],
        logs: false,
    },
};
