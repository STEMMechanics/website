<template>
    <SMPage class="sm-page-user-edit">
        <template #container>
            <SMHeading :heading="pageHeading" />
            <SMForm :model-value="form" @submit="handleSubmit">
                <SMRow>
                    <SMColumn><SMInput control="first_name" /></SMColumn>
                    <SMColumn><SMInput control="last_name" /></SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn><SMInput control="email" /></SMColumn>
                    <SMColumn><SMInput control="phone" /></SMColumn>
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
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, reactive } from "vue";
import { useRoute } from "vue-router";
import { openDialog } from "vue3-promise-dialog";
import SMDialogChangePassword from "../../components/dialogs/SMDialogChangePassword.vue";
import SMButton from "../../components/SMButton.vue";
import SMForm from "../../components/SMForm.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { UserResponse } from "../../helpers/api.types";
import { Form, FormControl } from "../../helpers/form";
import { And, Email, Phone, Required } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";

const route = useRoute();
const userStore = useUserStore();

const form = reactive(
    Form({
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
                url: "users/{id}",
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
            url: "users/{id}",
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

        form.message("Your details have been updated", "success");
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
