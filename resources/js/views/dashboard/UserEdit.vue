<template>
    <SMPageStatus
        v-if="!userHasPermission('admin/users') && route.params.id"
        :status="403" />
    <template v-else>
        <SMMastHead
            :title="pageHeading"
            :back-link="
                route.params.id || isCreating
                    ? { name: 'dashboard-user-list' }
                    : { name: 'dashboard' }
            "
            :back-title="
                route.params.id || isCreating
                    ? 'Back to Users'
                    : 'Back to Dashboard'
            " />
        <SMLoading v-if="form.loading()" />
        <div v-else class="max-w-4xl mx-auto px-4 mt-8">
            <SMForm :model-value="form" @submit="handleSubmit">
                <SMInput class="mb-4" control="display_name" autofocus />
                <SMInput class="mb-4" control="email" type="email" />
                <SMInput class="mb-4" control="first_name"
                    >This field is optional</SMInput
                >
                <SMInput class="mb-4" control="last_name"
                    >This field is optional</SMInput
                >
                <SMInput class="mb-4" control="phone"
                    >This field is optional</SMInput
                >
                <template v-if="userStore.permissions.includes('admin/users')">
                    <h2 class="mt-8">Permissions</h2>
                    <SMCheckbox
                        label="Edit Users"
                        class="mt-4"
                        v-model="permissions.users" /><SMCheckbox
                        class="mt-4"
                        label="Edit Articles"
                        v-model="permissions.articles" /><SMCheckbox
                        class="mt-4"
                        label="Edit Events"
                        v-model="permissions.events" />
                </template>
                <div class="flex flex-justify-between mt-8">
                    <button
                        type="button"
                        v-if="!isCreating"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-white border-1 border-sky-500 text-sky-500 cursor-pointer"
                        @click="handleChangePassword">
                        Change Password
                    </button>
                    <input
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        :value="computedSubmitLabel" />
                </div>
            </SMForm>
        </div>
        <div id="card-container"></div>
    </template>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import SMDialogChangePassword from "../../components/dialogs/SMDialogChangePassword.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { UserResponse } from "../../helpers/api.types";
import { Form, FormControl } from "../../helpers/form";
import { And, Custom, Email, Phone, Required } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";
import { userHasPermission } from "../../helpers/utils";
import SMLoading from "../../components/SMLoading.vue";
import SMPageStatus from "../../components/SMPageStatus.vue";
import SMCheckbox from "../../components/SMCheckbox.vue";

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const isCreating = route.path.endsWith("/create");

const customRequire = async (value) => {
    const control_names = ["display_name", "first_name", "last_name", "phone"];

    if (value.length == 0) {
        if (
            userHasPermission("admin/users") &&
            control_names.every((item) => form.controls[item].value.length == 0)
        ) {
            control_names.forEach((item) => {
                form.controls[item].clearValidations();
            });
            return true;
        }

        return "This field is required.";
    }

    return true;
};

let form = reactive(
    Form({
        display_name: FormControl("", Custom(customRequire)),
        first_name: FormControl("", Custom(customRequire)),
        last_name: FormControl("", Custom(customRequire)),
        email: FormControl("", And([Required(), Email()])),
        phone: FormControl("", Phone()),
    }),
);

const permissions = ref({
    users: false,
    articles: false,
    events: false,
});

/**
 * Load the page data.
 */
const loadData = async () => {
    if (route.params.id) {
        try {
            form.loading(true);
            const result = await api.get({
                url: "/users/{id}",
                params: {
                    id: route.params.id,
                },
            });

            const data = result.data as UserResponse;

            if (data && data.user) {
                form.controls.first_name.value = data.user.first_name;
                form.controls.last_name.value = data.user.last_name;
                form.controls.display_name.value = data.user.display_name;
                form.controls.phone.value = data.user.phone;
                form.controls.email.value = data.user.email;
            }
        } catch (error) {
            form.apiErrors(error, (message) => {
                useToastStore().addToast({
                    title: "An error occurred",
                    content: message,
                    type: "danger",
                });
            });
        } finally {
            form.loading(false);
        }
    } else if (isCreating == false) {
        form.controls.first_name.value = userStore.firstName;
        form.controls.last_name.value = userStore.lastName;
        form.controls.display_name.value = userStore.displayName;
        form.controls.phone.value = userStore.phone;
        form.controls.email.value = userStore.email;
    }
};

/**
 * Handle the user submitting the form.
 * @param enableFormCallBack
 */
const handleSubmit = async (enableFormCallBack) => {
    try {
        form.loading(true);
        const id = route.params.id ? route.params.id : userStore.id;

        if (isCreating == false) {
            const result = await api.put({
                url: "/users/{id}",
                params: {
                    id: id,
                },
                body: {
                    first_name: form.controls.first_name.value,
                    last_name: form.controls.last_name.value,
                    display_name: form.controls.display_name.value,
                    email: form.controls.email.value,
                    phone: form.controls.phone.value,
                },
            });

            const data = result.data as UserResponse;

            if (route.params.id && data && data.user) {
                userStore.setUserDetails(data.user);
            }

            useToastStore().addToast({
                title: "Details Updated",
                content: "The user has been updated.",
                type: "success",
            });
        } else {
            await api.post({
                url: "/users",
                params: {
                    id: id,
                },
                body: {
                    first_name: form.controls.first_name.value,
                    last_name: form.controls.last_name.value,
                    display_name: form.controls.display_name.value,
                    email: form.controls.email.value,
                    phone: form.controls.phone.value,
                },
            });

            useToastStore().addToast({
                title: "User Created",
                content: "The user has been created.",
                type: "success",
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get("return");
        if (returnUrl) {
            router.push(decodeURIComponent(returnUrl));
        } else {
            router.push({ name: "dashboard-user-list" });
        }
    } catch (error) {
        form.apiErrors(error, (message) => {
            useToastStore().addToast({
                title: "An error occurred",
                content: message,
                type: "danger",
            });
        });

        enableFormCallBack();
    } finally {
        form.loading(false);
    }
};

const handleChangePassword = () => {
    openDialog(SMDialogChangePassword);
};

const pageHeading = computed(() => {
    return route.params.id == null || route.params.id == userStore.id
        ? "My Details"
        : "User Details";
});

const computedSubmitLabel = computed(() => {
    return isCreating ? "Create" : "Update";
});

loadData();
// initCard();
</script>
