import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import analyzer from "rollup-plugin-analyzer";
import { compression } from "vite-plugin-compression2";
import { defineConfig } from "vite";
import Unocss from "unocss/vite";

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => ["ion-icon"].includes(tag),
                },
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        Unocss({}),
        laravel({
            input: ["resources/css/app.scss", "resources/js/main.js"],
            refresh: true,
        }),
        analyzer({ summaryOnly: true }),
        compression({
            include: [/\.(js)$/, /\.(css)$/],
            // deleteOriginalAssets: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // additionalData: `@import "./resources/css/variables.scss";`,
            },
        },
    },
    envPrefix: ["VITE_", "APP_URL"],
    resolve: {
        alias: {
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
    build: {
        chunkSizeWarningLimit: 500,
        rollupOptions: {
            output: {},
        },
    },
    base: "",
});
