import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            protocol: 'wss',
            host: 'test.stemmechanics.com.au',
            clientPort: 443,
            // port: 443
            path: '/vite-hmr'
        },
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
