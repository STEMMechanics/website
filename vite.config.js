import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/classroom.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
