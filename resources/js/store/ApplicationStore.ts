import { defineStore } from "pinia";

export interface ApplicationStore {
    dynamicTitle: string;
    racers: boolean;
}

export const useApplicationStore = defineStore({
    id: "application",
    state: (): ApplicationStore => ({
        dynamicTitle: "",
        racers: false,
    }),

    actions: {
        async setDynamicTitle(title: string) {
            this.$state.dynamicTitle = title;
            document.title = "STEMMechanics | " + title;
        },

        clearDynamicTitle() {
            this.$state.dynamicTitle = "";
        },
    },
});
