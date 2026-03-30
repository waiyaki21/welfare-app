import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite'; // New v4 plugin

export default defineConfig({
    plugins: [
        tailwindcss(), // This replaces the old PostCSS requirement
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});