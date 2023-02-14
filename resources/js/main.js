import "./bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import Router from "@/router";
// import "./axios.js";
import "normalize.css";
import "../css/app.scss";
import App from "./views/App.vue";
// import FontAwesomeIcon from "@/helpers/fontawesome";
import SMContainer from "./components/SMContainer.vue";
import SMRow from "./components/SMRow.vue";
import SMColumn from "./components/SMColumn.vue";
import { PromiseDialog } from "vue3-promise-dialog";
import { VueReCaptcha } from "vue-recaptcha-v3";
import "trix/dist/trix.css";

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

createApp(App)
    // .component("FontAwesomeIcon", FontAwesomeIcon)
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
    .mount("#app");
