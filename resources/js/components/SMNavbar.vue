<template>
    <SMContainer
        :full="true"
        :class="['navbar-container', { 'nav-active': showToggle }]"
        @click="handleClickNavBar">
        <template #inner>
            <nav class="navbar">
                <div id="nav-head">
                    <router-link :to="{ name: 'home' }" id="logo-link">
                        <img
                            class="nav-logo dark:d-none"
                            src="/assets/logo.webp"
                            width="270"
                            height="40"
                            alt="STEMMechanics" />
                        <img
                            class="nav-logo light:d-none"
                            src="/assets/logo-dark.webp"
                            width="270"
                            height="40"
                            alt="STEMMechanics" />
                    </router-link>
                    <div class="nav-right">
                        <SMButton
                            type="primary"
                            size="medium"
                            :to="{ name: 'workshops' }"
                            label="Find Workshops" />
                        <label
                            id="nav-toggle"
                            @click.stop="handleClickToggleMenu"
                            ><img
                                src="/assets/hamburger.svg"
                                width="24"
                                height="24"
                                alt="Navbar Toggle"
                        /></label>
                    </div>
                </div>
                <div id="nav">
                    <ul>
                        <template v-for="item in menuItems">
                            <li
                                v-if="item.show == undefined || item.show()"
                                :key="item.name">
                                <router-link :to="item.to"
                                    ><span>{{ item.label }}</span></router-link
                                >
                            </li>
                        </template>
                    </ul>
                </div>
            </nav>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useUserStore } from "../store/UserStore";
import SMButton from "../components/SMButton.vue";

const userStore = useUserStore();
const showToggle = ref(false);
const menuItems = [
    {
        name: "blog",
        label: "Blog",
        to: { name: "blog" },
    },
    {
        name: "workshops",
        label: "Workshops",
        to: { name: "workshops" },
    },
    {
        name: "community",
        label: "Community",
        to: { name: "community" },
    },
    {
        name: "contact",
        label: "Contact",
        to: { name: "contact" },
    },
    {
        name: "register",
        label: "Register",
        to: { name: "register" },
        show: () => !userStore.id,
    },
    {
        name: "login",
        label: "Log in",
        to: { name: "login" },
        show: () => !userStore.id,
    },
    {
        name: "dashboard",
        label: "Dashboard",
        to: { name: "dashboard" },
        show: () => userStore.id,
    },
    {
        name: "logout",
        label: "Log out",
        to: { name: "logout" },
        show: () => userStore.id,
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

const handleClickBody = (event: MouseEvent) => {
    const header = document.querySelector("header");
    const navbarContainer = document.querySelector(".navbar-container");
    if (
        !header?.contains(event.target as Node) &&
        !navbarContainer?.contains(event.target as Node)
    ) {
        if (showToggle.value == true) {
            handleClickToggleMenu();
        }
    }
};

onMounted(() => {
    document.body.addEventListener("click", handleClickBody);
});

onUnmounted(() => {
    document.body.removeEventListener("click", handleClickBody);
});
</script>

<style lang="scss">
.page-home {
    .navbar-container {
        background-color: rgba(255, 255, 255, 0.1);

        &:not(.nav-active) {
            .nav-logo.dark\:d-none {
                display: none !important;
            }

            .nav-logo.light\:d-none {
                display: block !important;
            }

            .navbar #nav-head #nav-toggle {
                filter: invert(100%) saturate(0%) brightness(120%);
            }
        }
    }
}

.navbar-container {
    position: relative;
    z-index: 100;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
    background-color: var(--navbar-color);
    box-shadow: var(--navbar-shadow);

    &.nav-active {
        background-color: var(--navbar-color) !important;

        #nav {
            max-height: 100vh;
            transition: max-height 0.4s linear;
        }

        #nav-toggle {
            background-color: hsla(0, 0%, 50%, 0.1);
        }
    }

    .navbar {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }

    #nav-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;

        #logo-link {
            padding-right: 18px;
            margin-top: -10px;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;

            &:hover {
                filter: none;
            }

            img {
                display: block;
            }
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        #nav-toggle {
            padding: 24px;
            cursor: pointer;
        }
    }

    #nav {
        display: flex;
        flex-direction: column;
        width: 100%;
        font-weight: 800;
        transition: max-height 0;
        height: auto;
        max-height: 0;
        overflow: hidden;

        ul {
            display: block;
            width: 100%;
            list-style-type: none;
            margin: 0;
            padding: 0 0 12px 0;

            li {
                margin-bottom: 0;

                a {
                    color: var(--base-color-text);
                    display: block;
                    padding: 12px 0;
                    margin: 0;
                    text-decoration: none;

                    &:hover {
                        text-decoration: none;
                        background-color: hsla(0, 0%, 50%, 0.1);
                    }

                    span {
                        padding-left: 12px;
                    }
                }
            }
        }
    }
}

@media (prefers-color-scheme: dark) {
    .navbar #nav-head #nav-toggle {
        filter: invert(100%) saturate(0%) brightness(120%);
    }
}

@media only screen and (max-width: 768px) {
    #nav-toggle {
        margin-right: -16px;
    }
}

@media screen and (max-width: 650px) {
    .nav-right {
        .button {
            display: none;
        }
    }
}
</style>
