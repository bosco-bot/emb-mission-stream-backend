import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/watch.css',
                'resources/js/watch-page.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            external: ['laravel-echo', 'pusher-js'],
        },
    },
});
