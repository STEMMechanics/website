import { createInertiaApp } from "@inertiajs/vue3";
import Toast, { POSITION } from "vue-toastification";
import "vue-toastification/dist/index.css";
import { createApp, h } from "vue/dist/vue.esm-bundler.js";
import "./bootstrap";

createInertiaApp({
    title: (title) => `${title} - STEMMechanics`,
    progress: {
        delay: 250,
        color: "#22C55E",
        includeCSS: true,
        showSpinner: false,
    },
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.vue", { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Toast, {
                position: POSITION.TOP_CENTER,
                toastClassName: "stemmechanics-toast",
                transition: "toast",
                hideProgressBar: true,
            })
            .mixin({ methods: { route } })
            .directive("inject-svg", {
                mounted(el) {
                    window.SVGInject(el);
                },
                updated(el) {
                    window.SVGInject(el);
                },
            })
            .mount(el);
    },
});
