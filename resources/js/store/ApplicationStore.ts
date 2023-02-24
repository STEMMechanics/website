import { defineStore } from "pinia";

export interface ApplicationStore {
    dynamicTitle: string;
}

export const useApplicationStore = defineStore({
    id: "application",
    state: (): ApplicationStore => ({
        dynamicTitle: "",
    }),

    actions: {
        async setDynamicTitle(title: string) {
            this.$state.dynamicTitle = title;
            document.title = "STEMMechanics | " + title;
        },

        clearDynamicTitle() {
            this.$state.dynamicTitle = "";
        },

        setRouterLoading(loading: boolean) {
            this.$state.routerLoading = loading;
        },
    },
});
