import { api } from "../helpers/api";
import { defineStore } from "pinia";

export interface UserDetails {
    id: string;
    username: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    permissions: string[];
}

export interface UserStore {
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
    state: (): UserStore => ({
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

        async fetchUser() {
            const res = await api.get("/users/" + this.$state.id);

            this.$state.id = res.json.user.id;
            this.$state.token = res.json.token;
            this.$state.username = res.json.user.username;
            this.$state.firstName = res.json.user.first_name;
            this.$state.lastName = res.json.user.last_name;
            this.$state.email = res.json.user.email;
            this.$state.phone = res.json.user.phone;
            this.$state.permissions = res.json.user.permissions || [];
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
});
