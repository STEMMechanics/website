import { defineStore } from "pinia";

export interface ToastOptions {
    id?: number;
    title?: string;
    content: string;
    type?: string;
    loader?: boolean;
}

export interface ToastItem {
    id: number;
    title: string;
    content: string;
    type: string;
    loader: boolean;
}

export interface ToastStore {
    toasts: ToastItem[];
}

export const defaultToastItem: ToastItem = {
    id: 0,
    title: "",
    content: "",
    type: "primary",
    loader: false,
};

export const useToastStore = defineStore({
    id: "toasts",
    state: (): ToastStore => ({
        toasts: [],
    }),

    actions: {
        addToast(toast: ToastOptions): number {
            while (
                !toast.id ||
                toast.id == 0 ||
                this.toasts.find((item: ToastItem) => item.id === toast.id)
            ) {
                toast.id =
                    Math.floor(Math.random() * Number.MAX_SAFE_INTEGER) + 1;
            }

            toast.title = toast.title || defaultToastItem.title;
            toast.type = toast.type || defaultToastItem.type;

            this.toasts.push(toast);
            return toast.id;
        },

        clearToast(id: number): void {
            this.toasts = this.toasts.filter(
                (item: ToastItem) => item.id !== id
            );
        },

        updateToast(id: number, updatedFields: Partial<ToastOptions>): void {
            const toastToUpdate = this.toasts.find(
                (item: ToastItem) => item.id === id
            );

            if (toastToUpdate) {
                toastToUpdate.title =
                    updatedFields.title || toastToUpdate.title;
                toastToUpdate.content =
                    updatedFields.content || toastToUpdate.content;
                toastToUpdate.type = updatedFields.type || toastToUpdate.type;
                if (
                    Object.prototype.hasOwnProperty.call(
                        updatedFields,
                        "loader"
                    )
                ) {
                    toastToUpdate.loader = updatedFields.loader;
                }
            }
        },
    },
});
