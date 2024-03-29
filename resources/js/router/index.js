import { useUserStore } from "@/store/UserStore";
import { createRouter, createWebHistory } from "vue-router";
import { api } from "../helpers/api";
import { useApplicationStore } from "../store/ApplicationStore";
import { updateSEOTags } from "../helpers/seo";

export const routes = [
    {
        path: "/",
        name: "home",
        meta: {
            title: "Home",
            description:
                "STEMMechanics, a family-run company based in Cairns, Queensland, creates fantastic STEM-focused programs and activities that are both entertaining and educational.",
        },
        component: () => import("@/views/Home.vue"),
    },
    {
        path: "/blog",
        name: "blog",
        meta: {
            title: "Blog",
        },
        component: () => import(/* webpackPrefetch: true */ "@/views/Blog.vue"),
    },
    {
        path: "/article",
        redirect: "/blog",
        children: [
            {
                path: ":slug",
                name: "article",
                component: () => import("@/views/Article.vue"),
            },
        ],
    },
    {
        path: "/workshops",
        name: "workshops",
        meta: {
            title: "Workshops",
        },
        component: () =>
            import(/* webpackPreload: true */ "@/views/Workshops.vue"),
    },
    {
        path: "/event",
        redirect: "/workshops",
        children: [
            {
                path: ":id",
                name: "event",
                component: () => import("@/views/Event.vue"),
            },
        ],
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
        path: "/community",
        name: "community",
        meta: {
            title: "Community",
        },
        component: () => import("@/views/Community.vue"),
    },
    {
        path: "/minecraft",
        children: [
            {
                path: "",
                name: "minecraft",
                meta: {
                    title: "Minecraft",
                },
                component: () => import("@/views/Minecraft.vue"),
            },
            {
                path: "curve",
                name: "minecraft-curve",
                meta: {
                    title: "Minecraft Curve",
                },
                component: () => import("@/views/MinecraftCurve.vue"),
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
        component: () =>
            import(/* webpackPrefetch: true */ "@/views/Login.vue"),
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
            title: "Contact",
        },
        component: () =>
            import(/* webpackPrefetch: true */ "@/views/Contact.vue"),
    },
    {
        path: "/conduct",
        redirect: { name: "code-of-conduct" },
    },
    {
        path: "/code-of-conduct",
        name: "code-of-conduct",
        meta: {
            title: "Code of Conduct",
        },
        component: () => import("@/views/CodeOfConduct.vue"),
    },
    {
        path: "/terms",
        redirect: { name: "terms-and-conditions" },
    },
    {
        path: "/terms-and-conditions",
        name: "terms-and-conditions",
        meta: {
            title: "Terms and Conditions",
        },
        component: () => import("@/views/TermsAndConditions.vue"),
    },
    {
        path: "/register",
        name: "register",
        meta: {
            title: "Register",
        },
        component: () =>
            import(/* webpackPrefetch: true */ "@/views/Register.vue"),
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
                component: () =>
                    import(
                        /* webpackPrefetch: true */ "@/views/dashboard/Dashboard.vue"
                    ),
            },
            {
                path: "analytics",
                children: [
                    {
                        path: "",
                        name: "dashboard-analytics-list",
                        meta: {
                            title: "Analytics",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/AnalyticsList.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-analytics-item",
                        meta: {
                            title: "Analytics Session",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/AnalyticsItem.vue"),
                    },
                ],
            },
            {
                path: "articles",
                children: [
                    {
                        path: "",
                        name: "dashboard-article-list",
                        meta: {
                            title: "Articles",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ArticleList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-article-create",
                        meta: {
                            title: "Create Article",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ArticleEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-article-edit",
                        meta: {
                            title: "Edit Article",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ArticleEdit.vue"),
                    },
                ],
            },
            {
                path: "events",
                children: [
                    {
                        path: "",
                        name: "dashboard-event-list",
                        meta: {
                            title: "Events",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/EventList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-event-create",
                        meta: {
                            title: "Create Event",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/EventEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-event-edit",
                        meta: {
                            title: "Event",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/EventEdit.vue"),
                    },
                ],
            },
            {
                path: "details",
                name: "dashboard-account-details",
                meta: {
                    title: "Account Details",
                    middleware: "authenticated",
                },
                component: () => import("@/views/dashboard/UserEdit.vue"),
            },
            {
                path: "users",
                children: [
                    {
                        path: "",
                        name: "dashboard-user-list",
                        meta: {
                            title: "Users",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/UserList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-user-create",
                        meta: {
                            title: "Create User",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/UserEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-user-edit",
                        meta: {
                            title: "Edit User",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/UserEdit.vue"),
                    },
                ],
            },
            {
                path: "media",
                children: [
                    {
                        path: "",
                        name: "dashboard-media-list",
                        meta: {
                            title: "Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/MediaList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-media-create",
                        meta: {
                            title: "Upload Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/MediaEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-media-edit",
                        meta: {
                            title: "Edit Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/MediaEdit.vue"),
                    },
                ],
            },
            {
                path: "shortlinks",
                children: [
                    {
                        path: "",
                        name: "dashboard-shortlink-list",
                        meta: {
                            title: "Shortlink",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ShortlinkList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-shortlink-create",
                        meta: {
                            title: "Create Shortlink",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ShortlinkEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-shortlink-edit",
                        meta: {
                            title: "Edit Shortlink",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/ShortlinkEdit.vue"),
                    },
                ],
            },
            {
                path: "discord-bot-logs",
                name: "dashboard-discord-bot-logs",
                meta: {
                    title: "Discord Bot Logs",
                    middleware: "authenticated",
                },
                component: () => import("@/views/dashboard/DiscordBotLogs.vue"),
            },
        ],
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
        path: "/file/:id",
        name: "file",
        meta: {
            title: "File",
        },
        component: () => import("@/views/File.vue"),
    },
    {
        path: "/cart",
        name: "cart",
        meta: {
            title: "Cart",
        },
        component: () => import("@/views/Cart.vue"),
    },
    {
        path: "/:catchAll(.*)",
        name: "not-found",
        meta: {
            title: "Page not found",
            hideInEditor: true,
        },
        component: () => import("@/views/404.vue"),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

// export let activeRoutes = [];

router.beforeEach(async (to, from, next) => {
    const userStore = useUserStore();
    const applicationStore = useApplicationStore();

    applicationStore.hydrated = false;
    applicationStore.clearDynamicTitle();

    if (applicationStore.pageLoaderTimeout !== 0) {
        window.clearTimeout(applicationStore.pageLoaderTimeout);
        applicationStore.pageLoaderTimeout = window.setTimeout(() => {
            const pageLoadingElem = document.getElementById("sm-page-loading");
            if (pageLoadingElem !== null) {
                pageLoadingElem.style.display = "flex";
            }
        }, 0);
    }

    if (to.meta.middleware == "authenticated") {
        if (userStore.id) {
            api.get({
                url: "/me",
            })
                .then((res) => {
                    userStore.setUserDetails(res.data.user);
                })
                .catch((err) => {
                    console.log(err);
                    if (err.status == 401) {
                        userStore.clearUser();

                        window.location.href = `/login?redirect=${to.fullPath}`;
                    }
                });
        }

        if (!userStore.id) {
            next({
                name: "login",
                query: { redirect: encodeURIComponent(to.fullPath) },
            });

            return;
        }
    }

    api.post({
        url: "/analytics",
        body: {
            type: "pageview",
            path: to.fullPath,
        },
    }).catch(() => {
        /* empty */
    });

    next();
});

router.afterEach((to, from) => {
    const applicationStore = useApplicationStore();

    if (from.name !== undefined) {
        document.body.classList.remove(`page-${from.name}`);
    }
    document.body.classList.add(`page-${to.name}`);

    window.setTimeout(() => {
        const getMetaValue = (tag, defaultValue = "") => {
            const getMeta = (obj, tag) => {
                const tagHierarchy = tag.split(".");

                const nearestWithMeta = obj.matched
                    .slice()
                    .reverse()
                    .reduce(
                        (acc, r) => acc || (r.meta && r.meta[tagHierarchy[0]]),
                        null,
                    );
                if (nearestWithMeta) {
                    let result = nearestWithMeta;
                    for (let i = 1; i < tagHierarchy.length; i++) {
                        result = result[tagHierarchy[i]];
                        if (!result) break;
                    }
                    if (result !== undefined) return result;
                }

                return null;
            };

            const nearestMeta = getMeta(to, tag);
            if (nearestMeta == null) {
                const previousMeta = getMeta(from, tag);
                if (previousMeta == null) {
                    return defaultValue;
                }

                return previousMeta;
            }
            return nearestMeta;
        };

        updateSEOTags({
            title: getMetaValue("title"),
            description: getMetaValue("description"),
            keywords: getMetaValue("keywords", []),
            robots: {
                index: getMetaValue(
                    "robots.index",
                    !to.meta.middleware
                        ? true
                        : to.meta.middleware != "authenticated",
                ),
                follow: getMetaValue(
                    "robots.follow",
                    !to.meta.middleware
                        ? true
                        : to.meta.middleware != "authenticated",
                ),
            },
            url: getMetaValue("url", to.path),
            image: getMetaValue("image", ""),
        });
    }, 10);

    window.setTimeout(() => {
        const autofocusElement = document.querySelector("[autofocus]");
        if (autofocusElement) {
            autofocusElement.focus();
        }

        const hash = window.location.hash;
        if (hash) {
            const target = document.querySelector(hash);
            if (target) {
                target.scrollIntoView();
            }
        }
    }, 10);

    if (applicationStore.pageLoaderTimeout !== 0) {
        window.clearTimeout(applicationStore.pageLoaderTimeout);
        applicationStore.pageLoaderTimeout = 0;
    }

    const pageLoadingElem = document.getElementById("sm-page-loading");
    if (pageLoadingElem !== null) {
        pageLoadingElem.style.display = "none";
    }

    applicationStore.hydrated = true;
});

export default router;
