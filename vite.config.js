import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import { viteStaticCopy } from "vite-plugin-static-copy";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");

    const appUrl = (env.APP_URL || "http://localhost").replace(/\/$/, "");
    const host = new URL(appUrl).hostname;
    const isProduction = mode === "production";

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

            viteStaticCopy({
                targets: [
                    {
                        src: "node_modules/tinymce/**/*",
                        dest: "tinymce",
                    },
                ],
            }),
        ],

        build: {
            sourcemap: false,
            cssCodeSplit: true,
            minify: isProduction ? "esbuild" : false,
            target: "es2018",
            rollupOptions: {
                output: {
                    chunkFileNames: "assets/[name]-[hash].js",
                    entryFileNames: "assets/[name]-[hash].js",
                    assetFileNames: "assets/[name]-[hash].[ext]",
                },
            },
        },

        server: {
            host: true,
            port: 5173,
            strictPort: true,
            origin: `${appUrl}:5173`,
            hmr: {
                host,
                port: 5173,
            },
            cors: true,
        },
    };
});
