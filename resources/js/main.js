import "./bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import Router from "@/router";
import "normalize.css";
import "../css/app.scss";
import App from "./views/App.vue";
import SMContainer from "./components/SMContainer.vue";
import SMRow from "./components/SMRow.vue";
import SMColumn from "./components/SMColumn.vue";
import { PromiseDialog } from "vue3-promise-dialog";
import { VueReCaptcha } from "vue-recaptcha-v3";
import "./lib/prism";
import { Vue3ProgressPlugin } from "@marcoschulte/vue3-progress";

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

createApp(App)
    .use(pinia)
    .use(Router)
    .use(PromiseDialog)
    .use(VueReCaptcha, {
        siteKey: import.meta.env.GOOGLE_RECAPTCHA_SITE_KEY,
        loaderOptions: {
            autoHideBadge: true,
        },
    })
    .use(Vue3ProgressPlugin)
    .component("SMContainer", SMContainer)
    .component("SMRow", SMRow)
    .component("SMColumn", SMColumn)
    .mount("#app");
