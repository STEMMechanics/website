import { defineStore } from "pinia";

export interface ToastOptions {
    id?: number;
    title?: string;
    content: string;
    type?: string;
}

export interface ToastItem {
    id: number;
    title: string;
    content: string;
    type: string;
}

export interface ToastStore {
    toasts: ToastItem[];
}

export const defaultToastItem: ToastItem = {
    id: 0,
    title: "",
    content: "",
    type: "primary",
};

export const useToastStore = defineStore({
    id: "toasts",
    state: (): ToastStore => ({
        toasts: [],
    }),

    actions: {
        addToast(toast: ToastOptions) {
            if (!toast.id || toast.id == 0) {
                toast.id =
                    Math.floor(Math.random() * Number.MAX_SAFE_INTEGER) + 1;
            }

            toast.title = toast.title || defaultToastItem.title;
            toast.type = toast.type || defaultToastItem.type;

            this.toasts.push(toast);
        },

        clearToast(id: number) {
            this.toasts = this.toasts.filter(
                (item: ToastItem) => item.id !== id
            );
        },
    },
});
