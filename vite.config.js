import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0', // Bind to all interfaces inside the container
        port: 5173,
        cors: {
            // Explicitly allow your local dev environment port
            origin: ['http://localhost:8080', 'http://127.0.0.1:8080'],
            methods: ['GET', 'PATCH', 'PUT', 'POST', 'DELETE', 'OPTIONS'],
            allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With']
        },
        hmr: {
            host: 'localhost', // Force Laravel to generate localhost client URLs
        },
    },
});
