<template>
    <SMContainer
        :full="true"
        :class="['sm-navbar', { showDropdown: showToggle }]"
        @click="handleHideMenu">
        <template #inner>
            <div class="navbar-container">
                <router-link :to="{ name: 'home' }" class="brand"></router-link>
                <ul class="navmenu flex-fill">
                    <template v-for="item in menuItems">
                        <li
                            v-if="
                                item.inNav &&
                                (item.show == undefined || item.show())
                            "
                            :key="item.name">
                            <router-link :to="item.to">{{
                                item.label
                            }}</router-link>
                        </li>
                    </template>
                </ul>
                <SMButton
                    :to="{ name: 'workshop-list' }"
                    class="navbar-cta"
                    label="Find a workshop"
                    icon="arrow-forward-outline" />
                <div class="menuButton" @click.stop="handleToggleMenu">
                    <span>Menu</span
                    ><ion-icon
                        class="menuButtonIcon"
                        name="reorder-three-outline"></ion-icon>
                </div>
            </div>
        </template>
        <div class="navbar-dropdown-cover"></div>
        <ul class="navbar-dropdown">
            <li class="ml-auto">
                <div class="menuClose" @click.stop="handleToggleMenu">
                    <ion-icon name="close-outline"></ion-icon>
                </div>
            </li>
            <template v-for="item in menuItems">
                <li
                    v-if="item.show == undefined || item.show()"
                    :key="item.name">
                    <router-link :to="item.to"
                        ><ion-icon :name="item.icon" />{{
                            item.label
                        }}</router-link
                    >
                </li>
            </template>
        </ul>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useUserStore } from "../store/UserStore";
import SMButton from "./SMButton.vue";

const userStore = useUserStore();
const showToggle = ref(false);
const menuItems = [
    {
        name: "news",
        label: "News",
        to: "/news",
        icon: "newspaper-outline",
    },
    {
        name: "workshops",
        label: "Workshops",
        to: "/workshops",
        icon: "shapes-outline",
    },
    // {
    //     name: "courses",
    //     label: "Courses",
    //     to: "/courses",
    //     icon: "fa-solid fa-graduation-cap",
    // },
    {
        name: "contact",
        label: "Contact us",
        to: "/contact",
        icon: "mail-outline",
    },
    {
        name: "register",
        label: "Register",
        to: "/register",
        icon: "person-add-outline",
        show: () => !userStore.id,
        inNav: false,
    },
    {
        name: "login",
        label: "Log in",
        to: "/login",
        icon: "log-in-outline",
        show: () => !userStore.id,
        inNav: false,
    },
    {
        name: "dashboard",
        label: "Dashboard",
        to: "/dashboard",
        icon: "apps-outline",
        show: () => userStore.id,
        inNav: false,
    },
    {
        name: "logout",
        label: "Log out",
        to: "/logout",
        icon: "log-out-outline",
        show: () => userStore.id,
        inNav: false,
    },
];

const handleToggleMenu = () => {
    showToggle.value = !showToggle.value;
};

const handleHideMenu = () => {
    if (showToggle.value) {
        showToggle.value = false;
    }
};
</script>

<style lang="scss">
.sm-navbar {
    height: 4.5rem;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    position: relative;
    flex: 0 0 auto !important;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
    z-index: 1000;

    &.showDropdown {
        .navbar-dropdown-cover {
            visibility: visible;
            opacity: 1;
            transition: visibility 0.3s linear, opacity 0.3s linear;
        }

        .navbar-dropdown {
            margin-top: 0;
            transition: margin 0.5s ease-in-out;
        }
    }

    .navbar-dropdown-cover {
        position: fixed;
        visibility: hidden;
        z-index: 2000;
        opacity: 0;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .navbar-dropdown {
        position: fixed;
        z-index: 2001;
        top: 0;
        left: 0;
        right: 0;

        display: flex;
        flex-direction: column;
        padding: 0 2rem 1rem 2rem;
        background-color: #fff;
        justify-content: center;
        align-items: center;
        box-shadow: 0 4px 4px rgba(0, 0, 0, 0.25);
        list-style-type: none;
        margin: -500px 0 0 0;

        li {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;

            a {
                display: inline-block;
                width: map-get($spacer, 5) * 3;

                ion-icon {
                    padding-right: map-get($spacer, 1);
                    font-size: map-get($spacer, 4);
                    vertical-align: middle;
                }
            }
        }
    }

    .navmenu,
    .navbar-dropdown {
        padding-top: map-get($spacer, 4);

        li {
            // display: flex;
            // width: 100%;
            margin: 0 0.75rem;
            // justify-content: center;

            a {
                color: rgba(0, 0, 0, 0.8);
                text-decoration: none;
                font-size: 1rem;
                transition: color 0.1s;

                &:hover {
                    color: #409eff;
                }
            }
        }

        .menuClose ion-icon {
            cursor: pointer;
            font-size: map-get($spacer, 4);
            padding-left: map-get($spacer, 1);
        }
    }

    .navbar-container {
        display: flex;
        flex: 1;
        align-items: center;

        .brand {
            display: inline-block;
            background-image: url("/img/logo.png");
            background-position: left top;
            background-repeat: no-repeat;
            background-size: contain;
            // width: 16.5rem;
            // height: 3rem;
            width: 13.5rem;
            height: 2rem;
            // margin-bottom: 1rem;
        }

        .navmenu {
            flex: 1;
            display: flex;
            justify-content: end;
            list-style-type: none;
            padding: 0 1rem;
        }

        .menuButton {
            cursor: pointer;
            // display: none;
            align-items: center;
            font-size: 0.9rem;
            margin-left: 2rem;
            margin-right: 1rem;

            span {
                display: none;
            }

            .menuButtonIcon {
                margin-left: 0.5rem;
                font-size: map-get($spacer, 4);
            }
        }
    }

    .navbar-cta {
        font-size: 0.9rem;
        padding: 0.6rem 1.1rem;
    }
}

@media only screen and (max-width: 1200px) {
    .navbar .navbar-container {
        .navmenu li {
            display: none;
        }

        .menuButton {
            display: flex;

            span {
                // display: block;
            }
        }
    }
}

@media only screen and (max-width: 992px) {
    .navbar {
        height: 4.5rem;

        .navbar-dropdown-cover {
            margin-top: 4.5rem;
        }

        .navbar-container {
            .brand {
                width: 13.5rem;
                height: 2rem;
                margin-bottom: 0;
            }

            .navbar-cta {
                font-size: 0.9rem;
                padding: 0.5rem 0.75rem;
            }
        }
    }
}

@media only screen and (max-width: 640px) {
    .navbar {
        height: 4.5rem;

        .navbar-dropdown-cover {
            margin-top: 4.5rem;
        }

        .navbar-container {
            .brand {
                background-image: url("/img/logo-small.png");
                width: 3rem;
                height: 3rem;
            }

            .navbar-cta {
                font-size: 0.9rem;
                padding: 0.5rem 0.75rem;

                svg {
                    display: none;
                }
            }

            .menuButton {
                margin-left: 1rem;

                span {
                    display: none;
                }
            }
        }
    }
}

@keyframes fadeIn {
    0% {
        visibility: hidden;
    }

    100% {
        visibility: visible;
    }
}

@keyframes fadeOut {
    0% {
        visibility: visible;
    }

    100% {
        visibility: hidden;
    }
}
</style>
