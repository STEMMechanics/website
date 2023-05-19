import { defineStore } from "pinia";

type ApplicationStoreEventKeyUpCallback = (event: KeyboardEvent) => boolean;

export interface ApplicationStore {
    dynamicTitle: string;
    eventKeyUpStack: ApplicationStoreEventKeyUpCallback[];
    pageLoaderTimeout: number;
    _addedListener: boolean;
}

export const useApplicationStore = defineStore({
    id: "application",
    state: (): ApplicationStore => ({
        dynamicTitle: "",
        eventKeyUpStack: [],
        pageLoaderTimeout: 0,
        _addedListener: false,
    }),

    actions: {
        async setDynamicTitle(title: string) {
            this.$state.dynamicTitle = title;
            document.title = `STEMMechanics | ${title}`;
        },

        clearDynamicTitle() {
            this.$state.dynamicTitle = "";
        },

        addKeyUpListener(callback: ApplicationStoreEventKeyUpCallback) {
            this.eventKeyUpStack.push(callback);

            if (!this._addedListener) {
                document.addEventListener("keyup", (event: KeyboardEvent) => {
                    this.eventKeyUpStack.every(
                        (item: ApplicationStoreEventKeyUpCallback) => {
                            const result = item(event);
                            if (result) {
                                return false;
                            }

                            return true;
                        }
                    );
                });
            }
        },

        removeKeyUpListener(callback: ApplicationStoreEventKeyUpCallback) {
            this.eventKeyUpStack = this.eventKeyUpStack.filter(
                (item: ApplicationStoreEventKeyUpCallback) => item !== callback
            );
        },
    },
});
