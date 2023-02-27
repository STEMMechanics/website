import { defineStore, DefineStoreOptions } from "pinia";

export interface UserDetails {
    id: string;
    username: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    permissions: string[];
}

export interface UserState {
    id: string;
    token: string;
    username: string;
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    permissions: string[];
}

export const useUserStore = defineStore({
    id: "user",
    state: (): UserState => ({
        id: "",
        token: "",
        username: "",
        firstName: "",
        lastName: "",
        email: "",
        phone: "",
        permissions: [],
    }),

    actions: {
        async setUserDetails(user: UserDetails) {
            this.$state.id = user.id;
            this.$state.username = user.username;
            this.$state.firstName = user.first_name;
            this.$state.lastName = user.last_name;
            this.$state.email = user.email;
            this.$state.phone = user.phone;
            this.$state.permissions = user.permissions || [];
        },

        async setUserToken(token: string) {
            this.$state.token = token;
        },

        clearUser() {
            this.$state.id = null;
            this.$state.token = null;
            this.$state.username = null;
            this.$state.firstName = null;
            this.$state.lastName = null;
            this.$state.email = null;
            this.$state.phone = null;
            this.$state.permissions = [];
        },
    },

    persist: true,
} as DefineStoreOptions<string, unknown, unknown, unknown> & { persist?: boolean });
