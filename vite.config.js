import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");

    // Example: APP_URL=http://cms.test or http://localhost
    const appUrl = (env.APP_URL || "http://localhost").replace(/\/$/, "");
    const host = new URL(appUrl).hostname;

    return {
        plugins: [
            laravel({
                input: [
                    "resources/css/app.css",
                    "resources/js/app.js",
                    "resources/css/filament/admin/theme.css",
                    "resources/js/filament/monaco.js",
                ],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: true,
            port: 5173,
            strictPort: true,
            origin: `${appUrl}:5173`,
            hmr: { host, port: 5173 },
            cors: true,
        },
    };
});
