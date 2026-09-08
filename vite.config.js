import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chatbot/react-flow-builder.jsx',
            ],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('DM Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Titillium Web', {
                    weights: [700],
                }),
                bunny('Public Sans', {
                    weights: [600],
                }),
                bunny('Open Sans', {
                    weights: [400],
                }),
                bunny('Roboto', {
                    weights: [400],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
