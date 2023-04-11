<template>
    <SMContainer
        :full="true"
        :class="['sm-navbar', { 'sm-show-nav': showToggle }]"
        @click="handleClickNavBar">
        <template #default>
            <div id="sm-nav-head">
                <router-link :to="{ name: 'home' }" id="sm-logo-link">
                    <img
                        class="sm-nav-logo"
                        src="/assets/logo.png"
                        width="270"
                        height="40"
                        alt="STEMMechanics" />
                </router-link>
                <label id="sm-nav-toggle" @click.stop="handleClickToggleMenu"
                    ><img
                        src="/assets/hamburger.svg"
                        width="24"
                        height="24"
                        alt="Navbar Toggle"
                /></label>
            </div>
            <div id="sm-nav">
                <ul class="left">
                    <template v-for="item in menuItems">
                        <li
                            v-if="item.show == undefined || item.show()"
                            :key="item.name">
                            <router-link :to="item.to">{{
                                item.label
                            }}</router-link>
                        </li>
                    </template>
                </ul>
                <ul class="right"></ul>
            </div>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useUserStore } from "../store/UserStore";

const userStore = useUserStore();
const showToggle = ref(false);
const menuItems = [
    {
        name: "news",
        label: "News",
        to: { name: "post-list" },
        icon: "newspaper-outline",
    },
    {
        name: "workshops",
        label: "Workshops",
        to: { name: "event-list" },
        icon: "library-outline",
    },
    // {
    //     name: "courses",
    //     label: "Courses",
    //     to: "/courses",
    //     icon: "briefcase-outline",
    // },
    {
        name: "contact",
        label: "Contact",
        to: { name: "contact" },
        icon: "mail-outline",
    },
    {
        name: "register",
        label: "Register",
        to: { name: "register" },
        icon: "person-add-outline",
        show: () => !userStore.id,
        inNav: false,
    },
    {
        name: "login",
        label: "Log in",
        to: { name: "login" },
        icon: "log-in-outline",
        show: () => !userStore.id,
        inNav: false,
    },
    {
        name: "dashboard",
        label: "Dashboard",
        to: { name: "dashboard" },
        icon: "grid-outline",
        show: () => userStore.id,
        inNav: false,
    },
    {
        name: "logout",
        label: "Log out",
        to: { name: "logout" },
        icon: "log-out-outline",
        show: () => userStore.id,
        inNav: false,
    },
];

/**
 * Handle the user clicking an element to toggle the dropdown menu.
 */
const handleClickToggleMenu = () => {
    showToggle.value = !showToggle.value;
};

/**
 * Handle the user clicking an element to toggle the dropdown menu.
 */
const handleClickNavBar = () => {
    if (showToggle.value == true) {
        showToggle.value = false;
    }
};
</script>

<style lang="scss">
.sm-navbar {
    position: relative;
    z-index: 1000;
    background-color: rgb(134 144 154 / 15%);
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);

    &.sm-show-nav {
        background-color: #333639;

        #sm-nav {
            display: flex;
        }
    }

    #sm-nav-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        max-width: 1200px;

        #sm-logo-link {
            padding-left: 23px;

            img {
                display: block;
            }
        }
        #sm-nav-toggle {
            padding: 23px;
            filter: invert(100%) saturate(0%) brightness(120%);

            &:hover {
                background-color: rgba(255, 255, 255, 0.25);
            }

            img {
                display: block;
            }
        }
    }

    #sm-nav {
        display: none;
        flex-direction: column;
        width: 100%;
        font-weight: 800;
        padding-bottom: 12px;

        ul {
            display: block;
            width: 100%;
            list-style-type: none;
            margin: 0;
            padding: 0;

            li a {
                color: #ddd;
                display: block;
                padding: 12px 24px;
                margin: 0;

                &:hover {
                    text-decoration: none;
                    background-color: hsla(0, 0%, 50%, 0.1);
                }
            }
        }
    }
}
</style>
