import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import analyzer from "rollup-plugin-analyzer";
import { compression } from "vite-plugin-compression2";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) =>
                        ["trix-editor", "ion-icon"].includes(tag),
                },
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        laravel({
            input: ["resources/css/app.scss", "resources/js/main.js"],
            refresh: true,
        }),
        analyzer({ summaryOnly: true }),
        compression(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: `@import "./resources/css/variables.scss";`,
            },
        },
    },
    envPrefix: ["VITE_", "GOOGLE_RECAPTCHA_SITE_KEY", "APP_URL"],
    resolve: {
        alias: {
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
    publicDir: "public",
    build: {
        chunkSizeWarningLimit: 1600,
    },
});
