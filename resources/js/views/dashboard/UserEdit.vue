<template>
    <SMMastHead
        :title="pageHeading"
        :back-link="
            route.params.id
                ? { name: 'dashboard-user-list' }
                : { name: 'dashboard' }
        "
        :back-title="route.params.id ? 'Back to Users' : 'Back to Dashboard'" />
    <SMContainer>
        <SMForm :model-value="form" @submit="handleSubmit">
            <SMRow>
                <SMColumn><SMInput control="username" disabled /></SMColumn>
                <SMColumn><SMInput control="display_name" /></SMColumn>
            </SMRow>
            <SMRow>
                <SMColumn><SMInput control="first_name" /></SMColumn>
                <SMColumn><SMInput control="last_name" /></SMColumn>
            </SMRow>
            <SMRow>
                <SMColumn><SMInput control="email" /></SMColumn>
                <SMColumn
                    ><SMInput control="phone"
                        ><template #help
                            >This field is optional</template
                        ></SMInput
                    >
                </SMColumn>
            </SMRow>
            <template v-if="userStore.permissions.includes('admin/users')">
                <SMRow
                    ><SMColumn><h3>Permissions</h3></SMColumn></SMRow
                >
                <SMRow>
                    <SMColumn
                        ><SMCheckbox
                            label="Edit Users"
                            v-model="permissions.users"
                    /></SMColumn>
                    <SMColumn
                        ><SMCheckbox
                            label="Edit Posts"
                            v-model="permissions.users"
                    /></SMColumn>
                    <SMColumn
                        ><SMCheckbox
                            label="Edit Events"
                            v-model="permissions.users"
                    /></SMColumn>
                </SMRow>
            </template>
            <SMRow>
                <SMColumn>
                    <SMFormFooter>
                        <template #right>
                            <SMButton
                                type="secondary"
                                label="Change Password"
                                @click="handleChangePassword" />
                            <SMButton type="submit" label="Update" />
                        </template>
                    </SMFormFooter>
                </SMColumn>
            </SMRow>
        </SMForm>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import SMDialogChangePassword from "../../components/dialogs/SMDialogChangePassword.vue";
import SMButton from "../../components/SMButton.vue";
import SMForm from "../../components/SMForm.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { UserResponse } from "../../helpers/api.types";
import { Form, FormControl } from "../../helpers/form";
import { And, Email, Phone, Required } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";
import SMCheckbox from "../../components/SMCheckbox.vue";

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

let form = reactive(
    Form({
        username: FormControl("", And([Required()])),
        display_name: FormControl("", And([Required()])),
        first_name: FormControl("", And([Required()])),
        last_name: FormControl("", And([Required()])),
        email: FormControl("", And([Required(), Email()])),
        phone: FormControl("", Phone()),
    })
);

const permissions = ref({
    users: false,
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
                form.controls.username.value = data.user.username;
                form.controls.first_name.value = data.user.first_name;
                form.controls.last_name.value = data.user.last_name;
                form.controls.display_name.value = data.user.display_name;
                form.controls.phone.value = data.user.phone;
                form.controls.email.value = data.user.email;
            }
        } catch (err) {
            form.apiErrors(err);
        } finally {
            form.loading(false);
        }
    } else {
        form.controls.username.value = userStore.username;
        form.controls.first_name.value = userStore.firstName;
        form.controls.last_name.value = userStore.lastName;
        form.controls.display_name.value = userStore.displayName;
        form.controls.phone.value = userStore.phone;
        form.controls.email.value = userStore.email;
    }
};

/**
 * Handle the user submitting the form.
 */
const handleSubmit = async () => {
    try {
        form.loading(true);
        const result = await api.put({
            url: "/users/{id}",
            params: {
                id: userStore.id,
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

        if (data && data.user) {
            userStore.setUserDetails(data.user);
        }

        useToastStore().addToast({
            title: route.params.id ? "Details Updated" : "User Created",
            content: route.params.id
                ? "The user has been updated."
                : "The user has been created.",
            type: "success",
        });

        router.push({ name: "dashboard" });
    } catch (err) {
        form.apiErrors(err);
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

loadData();
</script>

<style lang="scss">
.page-dashboard-account-details {
    h3 {
        margin-top: 0;
        margin-bottom: 16px;
    }
}
</style>
