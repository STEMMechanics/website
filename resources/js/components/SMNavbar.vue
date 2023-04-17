<template>
    <SMContainer
        :full="true"
        :class="['sm-navbar-container', { 'sm-show-nav': showToggle }]"
        @click="handleClickNavBar">
        <template #inner>
            <nav class="sm-navbar">
                <div id="sm-nav-head">
                    <router-link :to="{ name: 'home' }" id="sm-logo-link">
                        <img
                            class="sm-nav-logo dark:d-none"
                            src="/assets/logo.png"
                            width="270"
                            height="40"
                            alt="STEMMechanics" />
                        <img
                            class="sm-nav-logo light:d-none"
                            src="/assets/logo-dark.png"
                            width="270"
                            height="40"
                            alt="STEMMechanics" />
                    </router-link>
                    <div class="sm-nav-right">
                        <SMButton
                            type="primary"
                            size="medium"
                            :to="{ name: 'event-list' }"
                            label="Find Workshops" />
                        <label
                            id="sm-nav-toggle"
                            @click.stop="handleClickToggleMenu"
                            ><img
                                src="/assets/hamburger.svg"
                                width="24"
                                height="24"
                                alt="Navbar Toggle"
                        /></label>
                    </div>
                </div>
                <div id="sm-nav">
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
import { ref } from "vue";
import { useUserStore } from "../store/UserStore";
import SMButton from "../components/SMButton.vue";

const userStore = useUserStore();
const showToggle = ref(false);
const menuItems = [
    {
        name: "workshops",
        label: "Workshops",
        to: { name: "event-list" },
    },
    {
        name: "blog",
        label: "Blog",
        to: { name: "blog" },
    },
    {
        name: "community",
        label: "Community",
        to: { name: "blog" },
    },
    {
        name: "about",
        label: "About",
        to: { name: "blog" },
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
    },
    {
        name: "register",
        label: "Register",
        to: { name: "register" },
        icon: "person-add-outline",
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
        icon: "grid-outline",
        show: () => userStore.id,
    },
    {
        name: "logout",
        label: "Log out",
        to: { name: "logout" },
        icon: "log-out-outline",
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
</script>

<style lang="scss">
body[data-route-name="page-home"] {
    .sm-navbar-container {
        background-color: rgba(255, 255, 255, 0.1);
    }
}

.sm-navbar-container {
    position: relative;
    z-index: 100;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
    background-color: var(--navbar-color);
    box-shadow: var(--base-shadow);

    &.sm-show-nav {
        background-color: var(--navbar-color) !important;

        #sm-nav {
            display: flex;
        }

        #sm-nav-toggle {
            background-color: hsla(0, 0%, 50%, 0.1);
        }
    }

    .sm-navbar {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }

    #sm-nav-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;

        #sm-logo-link {
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

        .sm-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .button {
            color: #fff;
        }

        #sm-nav-toggle {
            padding: 24px;
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

@media (prefers-color-scheme: dark) {
    .sm-navbar #sm-nav-head #sm-nav-toggle {
        filter: invert(100%) saturate(0%) brightness(120%);
    }
}

@media screen and (max-width: 768px) {
    // #sm-nav-toggle {
    //     padding: 23px;
    //     -webkit-user-select: none;
    //     -moz-user-select: none;
    //     -ms-user-select: none;
    //     user-select: none;

    //     &:hover {
    //         background-color: hsla(0, 0%, 50%, 0.1);
    //     }

    //     img {
    //         display: block;
    //     }
    // }
}
</style>
