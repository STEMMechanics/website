import axios, { AxiosStatic } from "axios";
import stemmech, { StemmechStatic } from "./stemmech";

// Attach axios to the window object
declare global {
    interface Window {
        axios: AxiosStatic;
        stemmech: StemmechStatic;
        SVGInject: any;
    }
}

window.axios = axios;
window.stemmech = stemmech;

// Set a default header for Axios requests
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Setup window ready
window.stemmech.ready(() => {
    setTimeout(function () {
        window.stemmech.cleanupBackLinks();
        window.stemmech.inputErrorListener();
        window.stemmech.formSubmitListener();
        window.stemmech.formChangeListener();
    }, 1);

    window.SVGInject(document.querySelectorAll("img.injectable"));
});
