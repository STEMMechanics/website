import axios from "axios";
import { useUserStore } from "./store/UserStore";
import { useRouter } from "vue-router";

axios.defaults.baseURL = import.meta.env.APP_URL_API;
axios.defaults.withCredentials = true;
axios.defaults.headers.common["Accept"] = "application/json";

axios.interceptors.request.use((request) => {
    const userStore = useUserStore();
    if (userStore.id) {
        request.headers["Authorization"] = `Bearer ${userStore.token}`;
    }

    return request;
});

axios.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        if (error.config.redirect !== false && error.response.status === 401) {
            const userStore = useUserStore();
            const router = useRouter();
            userStore.clearUser();

            const url = new URL(error.request.responseURL);
            router.push({ name: "login", query: { redirect: url.pathname } });
        }

        // if(error.config.redirect === true) {
        //     if(error.response.status === 403) {
        //         router.push({ name: 'error-forbidden' })
        //     } else if(error.response.status === 404) {
        //         router.push({ name: 'error-notfound' })
        //     } else if(error.response.status >= 500) {
        //         router.push({name: 'error-internal'})
        //     }
        // }

        return Promise.reject(error);
    }
);
