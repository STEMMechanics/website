<template>
    <SMPage>
        <SMRow>
            <SMDialog>
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
                        <SMFormFooter>
                            <template #right>
                                <SMButton
                                    type="secondary"
                                    label="Change Password"
                                    @click.prevent="handleChangePassword" />
                                <SMButton type="submit" label="Update" />
                            </template>
                        </SMFormFooter>
                    </SMRow>
                </SMForm>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, computed } from "vue";
import { api } from "../../helpers/api";
import { FormObject, FormControl } from "../../helpers/form";
import { And, Required, Email, Phone } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";
import { useRoute } from "vue-router";
import { openDialog } from "vue3-promise-dialog";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMForm from "../../components/SMForm.vue";
import SMPage from "../../components/SMPage.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import SMDialogChangePassword from "../../components/dialogs/SMDialogChangePassword.vue";

const route = useRoute();
const userStore = useUserStore();

const form = reactive(
    FormObject({
        first_name: FormControl("", And([Required()])),
        last_name: FormControl("", And([Required()])),
        email: FormControl("", And([Required(), Email()])),
        phone: FormControl("", Phone()),
    })
);

const loadData = async () => {
    if (route.params.id) {
        try {
            form.loading(true);
            let res = await api.get(`users/${route.params.id}`);

            form.first_name.value = res.data.user.first_name;
            form.last_name.value = res.data.user.last_name;
            form.phone.value = res.data.user.phone;
            form.email.value = res.data.user.email;
        } catch (err) {
            form.apiErrors(err);
        }
    } else {
        form.first_name.value = userStore.firstName;
        form.last_name.value = userStore.lastName;
        form.phone.value = userStore.phone;
        form.email.value = userStore.email;
    }

    form.loading(false);
};

const handleSubmit = async () => {
    try {
        form.loading(true);
        let res = await api.put({
            url: `users/${userStore.id}`,
            body: {
                first_name: form.first_name.value,
                last_name: form.last_name.value,
                email: form.email.value,
                phone: form.phone.value,
            },
        });

        userStore.setUserDetails(res.data.user);

        form.message("Your details have been updated", "success");
    } catch (err) {
        form.apiErrors(err);
    }

    form.loading(false);
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
