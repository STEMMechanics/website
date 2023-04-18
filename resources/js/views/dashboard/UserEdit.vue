<template>
    <SMMastHead
        :title="pageHeading"
        :back-link="{ name: 'dashboard' }"
        back-title="Back to Dashboard" />
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
                    ><SMInput control="phone">This field is optional</SMInput>
                </SMColumn>
            </SMRow>
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
import { computed, reactive } from "vue";
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
.sm-page-user-edit {
    background-color: #f8f8f8;
}
</style>
