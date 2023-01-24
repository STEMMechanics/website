import axios from "axios";
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
            const res = await axios.get("users/" + this.$state.id);

            this.$state.id = res.data.user.id;
            this.$state.token = res.data.token;
            this.$state.username = res.data.user.username;
            this.$state.firstName = res.data.user.first_name;
            this.$state.lastName = res.data.user.last_name;
            this.$state.email = res.data.user.email;
            this.$state.phone = res.data.user.phone;
            this.$state.permissions = res.data.user.permissions || [];
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
