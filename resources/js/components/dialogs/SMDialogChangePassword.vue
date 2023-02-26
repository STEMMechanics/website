<template>
    <SMModal>
        <SMDialog :loading="dialogLoading">
            <h1>Change Password</h1>
            <p class="text-center">Enter your new password below</p>
            <SMForm :model-value="form" @submit="handleSubmit">
                <SMInput
                    control="password"
                    type="password"
                    label="New Password" />
                <SMFormFooter>
                    <template #left>
                        <SMButton
                            type="secondary"
                            label="Cancel"
                            @click="handleClickCancel" />
                    </template>
                    <template #right>
                        <SMButton type="submit" label="Update" />
                    </template>
                </SMFormFooter>
            </SMForm>
        </SMDialog>
    </SMModal>
</template>

<script setup lang="ts">
import { api } from "../../helpers/api";
import { FormObject, FormControl } from "../../helpers/form";
import { And, Required, Password } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";
import { ref, reactive, onMounted, onUnmounted } from "vue";
import { useToastStore } from "../../store/ToastStore";
import { closeDialog } from "vue3-promise-dialog";
import SMModal from "../SMModal.vue";
import SMDialog from "../SMDialog.vue";
import SMForm from "../SMForm.vue";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMInput from "../SMInput.vue";

const form = reactive(
    FormObject({
        password: FormControl("", And([Required(), Password()])),
    })
);

const userStore = useUserStore();
const dialogLoading = ref(false);

const handleClickCancel = () => {
    closeDialog(false);
};

const handleSubmit = async () => {
    dialogLoading.value = true;

    api.put({
        url: `/users/${userStore.id}`,
        body: {
            password: form.password.value,
        },
    })
        .then(() => {
            const toastStore = useToastStore();

            toastStore.addToast({
                title: "Password Reset",
                content: "Your password has been reset",
                type: "success",
            });
            closeDialog(false);
        })
        .catch((error) => {
            form.apiErrors(error);
        })
        .finally(() => {
            dialogLoading.value = false;
        });
};

const eventKeyUp = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        handleClickCancel();
    }
};

onMounted(() => {
    document.addEventListener("keyup", eventKeyUp);
});

onUnmounted(() => {
    document.removeEventListener("keyup", eventKeyUp);
});
</script>
