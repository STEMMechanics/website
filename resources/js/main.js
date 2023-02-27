import Router from "@/router";
import "normalize.css";
import { createPinia } from "pinia";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import { createApp } from "vue";
import { VueReCaptcha } from "vue-recaptcha-v3";
import { PromiseDialog } from "vue3-promise-dialog";
import "../css/app.scss";
import "./bootstrap";
import SMColumn from "./components/SMColumn.vue";
import SMContainer from "./components/SMContainer.vue";
import SMPage from "./components/SMPage.vue";
import SMRow from "./components/SMRow.vue";
import "./lib/prism";
import App from "./views/App.vue";

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
    .component("SMContainer", SMContainer)
    .component("SMRow", SMRow)
    .component("SMColumn", SMColumn)
    .component("SMPage", SMPage)
    .mount("#app");
