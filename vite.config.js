import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/phoenix/app.scss', 'resources/js/phoenix/app.js'],
            refresh: true,
        }),
    ],
});
