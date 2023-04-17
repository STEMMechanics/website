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
        component: () => import("@/views/Blog.vue"),
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
        component: () => import("@/views/Workshops.vue"),
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
        path: "/minecraft",
        name: "minecraft",
        meta: {
            title: "Minecraft",
        },
        component: () => import("@/views/Minecraft.vue"),
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
            title: "Contact",
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
        path: "/dashboard",
        children: [
            {
                path: "",
                name: "dashboard",
                meta: {
                    title: "Dashboard",
                    middleware: "authenticated",
                },
                component: () => import("@/views/dashboard/Dashboard.vue"),
            },
            {
                path: "posts",
                children: [
                    {
                        path: "",
                        name: "dashboard-post-list",
                        meta: {
                            title: "Posts",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/PostList.vue"),
                    },
                    {
                        path: "create",
                        name: "dashboard-post-create",
                        meta: {
                            title: "Create Post",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/PostEdit.vue"),
                    },
                    {
                        path: ":id",
                        name: "dashboard-post-edit",
                        meta: {
                            title: "Edit Post",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/PostEdit.vue"),
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
                            title: "Event Post",
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
                        path: "upload",
                        name: "dashboard-media-upload",
                        meta: {
                            title: "Upload Media",
                            middleware: "authenticated",
                        },
                        component: () =>
                            import("@/views/dashboard/MediaEdit.vue"),
                    },
                    {
                        path: "edit/:id",
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
        path: "/error/internal",
        name: "error-internal",
        meta: {
            title: "Server error",
            hideInEditor: true,
        },
        component: () => import("@/components/errors/Internal.vue"),
    },
    {
        path: "/error/forbidden",
        name: "forbidden",
        meta: {
            title: "Forbidden",
            hideInEditor: true,
        },
        component: () => import("@/components/errors/Forbidden.vue"),
    },
    {
        path: "/:catchAll(.*)",
        name: "not-found",
        meta: {
            title: "Page not found",
            hideInEditor: true,
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
    const userStore = useUserStore();
    const applicationStore = useApplicationStore();

    applicationStore.clearDynamicTitle();

    // Check Token
    if (userStore.id) {
        let redirect = false;

        try {
            let res = await api.get("/me");
            userStore.setUserDetails(res.json.user);
        } catch (err) {
            if (err.status == 401) {
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

    const getMetaValue = (tag, defaultValue = "") => {
        const getMeta = (obj, tag) => {
            const tagHierarchy = tag.split(".");

            const nearestWithMeta = obj.matched
                .slice()
                .reverse()
                .reduce(
                    (acc, r) => acc || (r.meta && r.meta[tagHierarchy[0]]),
                    null
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
            index: getMetaValue("robots.index", true),
            follow: getMetaValue("robots.follow", true),
        },
        url: getMetaValue("url", to.path),
        image: getMetaValue("image", ""),
    });

    // Meta tags
    // const nearestWithMeta = to.matched
    //     .slice()
    //     .reverse()
    //     .find((r) => r.meta && r.meta.metaTags);
    // Array.from(document.querySelectorAll("[data-vue-router-controlled]")).map(
    //     (el) => el.parentNode.removeChild(el)
    // );
    // if (nearestWithMeta) {
    //     nearestWithMeta.meta.metaTags
    //         .map((tagDef) => {
    //             const tag = document.createElement("meta");

    //             Object.keys(tagDef).forEach((key) => {
    //                 tag.setAttribute(key, tagDef[key]);
    //             });

    //             tag.setAttribute("data-vue-router-controlled", "");

    //             return tag;
    //         })
    //         .forEach((tag) => document.head.appendChild(tag));
    // }

    if (to.meta.middleware == "authenticated" && !userStore.id) {
        next({ name: "login", query: { redirect: to.fullPath } });
    } else {
        next();
    }
});

router.afterEach((to, from) => {
    const routeName = `page-${to.name}`;
    document.body.dataset.routeName = routeName;
});

export default router;
