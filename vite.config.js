import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => ["trix-editor"].includes(tag),
                },
            },
        }),
        laravel({
            input: ["resources/css/app.scss", "resources/js/main.js"],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: `@import "./resources/css/variables.scss";`,
            },
        },
    },
    envPrefix: ["VITE_", "GOOGLE_RECAPTCHA_SITE_KEY", "APP_URL"],
    // resolve: {
    //     alias: {
    //         vue: 'vue/dist/vue.esm-bundler.js',
    //     },
    // },
    publicDir: "public",
});
