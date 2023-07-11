import { defineStore } from "pinia";

type ApplicationStoreEventKeyUpCallback = (event: KeyboardEvent) => boolean;
type ApplicationStoreEventKeyPressCallback = (event: KeyboardEvent) => boolean;

export interface ApplicationStore {
    hydrated: boolean;
    unavailable: boolean;
    dynamicTitle: string;
    eventKeyUpStack: ApplicationStoreEventKeyUpCallback[];
    eventKeyPressStack: ApplicationStoreEventKeyPressCallback[];
    pageLoaderTimeout: number;
    _addedListener: boolean;
}

export const useApplicationStore = defineStore({
    id: "application",
    state: (): ApplicationStore => ({
        hydrated: false,
        unavailable: false,
        dynamicTitle: "",
        eventKeyUpStack: [],
        eventKeyPressStack: [],
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
                        },
                    );
                });
            }
        },

        removeKeyUpListener(callback: ApplicationStoreEventKeyUpCallback) {
            this.eventKeyUpStack = this.eventKeyUpStack.filter(
                (item: ApplicationStoreEventKeyUpCallback) => item !== callback,
            );
        },

        addKeyPressListener(callback: ApplicationStoreEventKeyPressCallback) {
            this.eventKeyPressStack.push(callback);

            if (!this._addedListener) {
                document.addEventListener(
                    "keypress",
                    (event: KeyboardEvent) => {
                        this.eventKeyPressStack.every(
                            (item: ApplicationStoreEventKeyPressCallback) => {
                                const result = item(event);
                                if (result) {
                                    return false;
                                }

                                return true;
                            },
                        );
                    },
                );
            }
        },

        removeKeyPressListener(
            callback: ApplicationStoreEventKeyPressCallback,
        ) {
            this.eventKeyPressStack = this.eventKeyPressStack.filter(
                (item: ApplicationStoreEventKeyPressCallback) =>
                    item !== callback,
            );
        },
    },
});
