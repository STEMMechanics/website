import axios from "axios";
import { createWebHistory, createRouter } from "vue-router";
import { useUserStore } from "@/store/UserStore";
import { useApplicationStore } from "../store/ApplicationStore";

export const routes = [
    {
        path: "/",
        name: "home",
        meta: {
            title: "Home",
        },
        component: () => import("@/views/Home.vue"),
    },
    {
        path: "/get-file/:id",
        name: "get-file",
        component: () => import("@/views/GetFile.vue"),
    },
    {
        path: "/verify-email",
        name: "verify-email",
        meta: {
            title: "Verify Email",
        },
        component: () => import("@/views/EmailVerify.vue"),
    },
    {
        path: "/resend-verify-email",
        name: "resend-verify-email",
        meta: {
            title: "Resend Verification Email",
        },
        component: () => import("@/views/ResendEmailVerify.vue"),
    },
    {
        path: "/reset-password",
        name: "reset-password",
        meta: {
            title: "Reset Password",
        },
        component: () => import("@/views/ResetPassword.vue"),
    },
    {
        path: "/about",
        name: "about",
        meta: {
            title: "About",
        },
        component: () => import("@/views/About.vue"),
    },
    {
        path: "/privacy",
        name: "privacy",
        meta: {
            title: "Privacy Policy",
        },
        component: () => import("@/views/Privacy.vue"),
    },
    {
        path: "/rules",
        name: "rules",
        meta: {
            title: "Rules",
        },
        component: () => import("@/views/Rules.vue"),
    },
    {
        path: "/unsubscribe",
        name: "unsubscribe",
        meta: {
            title: "Unsubscribe",
        },
        component: () => import("@/views/Unsubscribe.vue"),
    },
    {
        path: "/terms",
        name: "terms",
        meta: {
            title: "Terms and Conditions",
        },
        component: () => import("@/views/Terms.vue"),
    },
    {
        path: "/workshops",
        children: [
            {
                path: "",
                name: "workshop-list",
                meta: {
                    title: "Workshops",
                },
                component: () => import("@/views/WorkshopList.vue"),
            },
            {
                path: ":id",
                name: "workshop-view",
                component: () => import("@/views/WorkshopView.vue"),
            },
        ],
    },
    {
        path: "/login",
        name: "login",
        meta: {
            title: "Login",
            middleware: "guest",
        },
        component: () => import("@/views/Login.vue"),
    },
    {
        path: "/logout",
        name: "logout",
        meta: {
            title: "Logout",
        },
        component: () => import("@/views/Logout.vue"),
    },
    {
        path: "/contact",
        name: "contact",
        meta: {
            title: "Contact Us",
        },
        component: () => import("@/views/Contact.vue"),
    },
    {
        path: "/register",
        name: "register",
        meta: {
            title: "Register",
        },
        component: () => import("@/views/Register.vue"),
    },
    {
        path: "/news",
        children: [
            {
                path: "",
                name: "news",
                meta: {
                    title: "News",
                },
                component: () => import("@/views/NewsList.vue"),
            },
            {
                path: ":slug",
                name: "post-view",
                component: () => import("@/views/NewsView.vue"),
            },
        ],
    },
    {
        path: "/dashboard",
        children: [
            {
                path: "",
                name: "dashboard",
                meta: {
                    title: "Dashboard",
                    middleware: "authenticated",
                },
                component: () => import("@/views/Dashboard.vue"),
            },
            {
                path: "posts",
                children: [
                    {
                        path: "",
                        name: "post-list",
                        meta: {
                            title: "Posts",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/PostList.vue"),
                    },
                    {
                        path: "create",
                        name: "post-create",
                        meta: {
                            title: "Create Post",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/PostEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "post-edit",
                        meta: {
                            title: "Edit Post",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/PostEdit.vue"),
                    },
                ],
            },
            {
                path: "events",
                children: [
                    {
                        path: "",
                        name: "event-list",
                        meta: {
                            title: "Events",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/EventList.vue"),
                    },
                    {
                        path: "create",
                        name: "event-create",
                        meta: {
                            title: "Create Event",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/EventEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "event-edit",
                        meta: {
                            title: "Event Post",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/EventEdit.vue"),
                    },
                ],
            },
            {
                path: "details",
                name: "account-details",
                meta: {
                    title: "Account Details",
                    middleware: "authenticated",
                },
                component: () => import("@/views/Dashboard/UserEdit.vue"),
            },
            {
                path: "users",
                children: [
                    {
                        path: "",
                        name: "user-list",
                        meta: {
                            title: "Users",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/UserList.vue"),
                    },
                    {
                        path: ":id",
                        name: "user-edit",
                        meta: {
                            title: "Edit User",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/UserEdit.vue"),
                    },
                ],
            },
            {
                path: "media",
                children: [
                    {
                        path: "",
                        name: "media",
                        meta: {
                            title: "Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/MediaList.vue"),
                    },
                    {
                        path: "upload",
                        name: "media-upload",
                        meta: {
                            title: "Upload Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/MediaEdit.vue"),
                    },
                    {
                        path: "edit/:id",
                        name: "media-edit",
                        meta: {
                            title: "Edit Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/Dashboard/MediaEdit.vue"),
                    },
                ],
            },
        ],
    },
    {
        path: "/forgot-username",
        name: "forgot-username",
        meta: {
            title: "Forgot Username",
        },
        component: () => import("@/views/ForgotUsername.vue"),
    },
    {
        path: "/forgot-password",
        name: "forgot-password",
        meta: {
            title: "Forgot Password",
        },
        component: () => import("@/views/ForgotPassword.vue"),
    },
    {
        path: "/courses",
        name: "courses",
        component: () => import("@/views/Courses.vue"),
    },
    {
        path: "/error/internal",
        name: "error-internal",
        meta: {
            title: "Server error",
        },
        component: () => import("@/views/errors/Internal.vue"),
    },
    {
        path: "/error/forbidden",
        name: "forbidden",
        meta: {
            title: "Forbidden",
        },
        component: () => import("@/components/errors/Forbidden.vue"),
    },
    {
        path: "/:catchAll(.*)",
        name: "not-found",
        meta: {
            title: "Page not found",
        },
        component: () => import("@/components/errors/NotFound.vue"),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        // always scroll to top
        return { top: 0 };
    },
});

// export let activeRoutes = [];

router.beforeEach(async (to, from, next) => {
    // BC Start
    // activeRoutes = [];
    // to.matched.forEach((record) => {
    //     console.log(record.routeName);
    //     activeRoutes.push(record);
    // });

    const userStore = useUserStore();
    const applicationStore = useApplicationStore();

    applicationStore.clearDynamicTitle();

    // Check Token
    if (userStore.id) {
        let redirect = false;

        try {
            let res = await axios.get("me");
            userStore.setUserDetails(res.data.user);
        } catch (err) {
            if (err.response.status == 401) {
                userStore.clearUser();
                redirect = true;
            }
        }

        if (
            redirect &&
            to.path.startsWith("/error/") === false &&
            to.path.startsWith("/login") === false
        ) {
            next({ name: "login", query: { redirect: to.fullPath } });
            return;
        }
    }

    // Document Title
    const nearestWithTitle = to.matched
        .slice()
        .reverse()
        .find((r) => r.meta && r.meta.title);
    const previousNearestWithMeta = from.matched
        .slice()
        .reverse()
        .find((r) => r.meta && r.meta.metaTags);

    let title = "";
    if (nearestWithTitle) {
        title = nearestWithTitle.meta.title;
    } else if (previousNearestWithMeta) {
        title = previousNearestWithMeta.meta.title;
    }

    if (title != "") {
        document.title = "STEMMechanics | " + title;
    }

    // Meta tags
    const nearestWithMeta = to.matched
        .slice()
        .reverse()
        .find((r) => r.meta && r.meta.metaTags);
    Array.from(document.querySelectorAll("[data-vue-router-controlled]")).map(
        (el) => el.parentNode.removeChild(el)
    );
    if (nearestWithMeta) {
        nearestWithMeta.meta.metaTags
            .map((tagDef) => {
                const tag = document.createElement("meta");

                Object.keys(tagDef).forEach((key) => {
                    tag.setAttribute(key, tagDef[key]);
                });

                tag.setAttribute("data-vue-router-controlled", "");

                return tag;
            })
            .forEach((tag) => document.head.appendChild(tag));
    }

    // Middleware
    // if (to.meta.middleware == 'guest' && userStore.id) {
    //     next({ name: 'home'})
    // } else
    if (to.meta.middleware == "authenticated" && !userStore.id) {
        next({ name: "login", query: { redirect: to.fullPath } });
    } else {
        next();
    }
});

export default router;
