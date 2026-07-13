import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const parsePort = (value, fallback) => {
    const port = Number.parseInt(String(value || ''), 10);

    return Number.isFinite(port) && port > 0 ? port : fallback;
};

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerUrl = env.VITE_DEV_SERVER_URL || 'http://127.0.0.1:5173';
    const devServer = new URL(devServerUrl);
    const isHttps = devServer.protocol === 'https:';
    const port = parsePort(env.VITE_DEV_SERVER_PORT || devServer.port, 5173);
    const hmrHost = env.VITE_DEV_SERVER_HMR_HOST || devServer.hostname;
    const allowedHosts = Array.from(new Set([
        devServer.hostname,
        hmrHost,
    ].filter(Boolean)));

    return {
        server: {
            host: env.VITE_DEV_SERVER_BIND_HOST || '0.0.0.0',
            port,
            strictPort: true,
            origin: devServer.origin,
            allowedHosts,
            hmr: {
                protocol: env.VITE_DEV_SERVER_HMR_PROTOCOL || (isHttps ? 'wss' : 'ws'),
                host: hmrHost,
                clientPort: parsePort(
                    env.VITE_DEV_SERVER_HMR_CLIENT_PORT || devServer.port,
                    isHttps ? 443 : 80,
                ),
                path: env.VITE_DEV_SERVER_HMR_PATH || '/vite-hmr',
            },
        },
        plugins: [
            laravel({
                hotFile: 'storage/framework/vite.hot',
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
    };
});
