<template>
    <SMForm :model-value="form" @submit="handleSubmit">
        <SMFormCard :loading="dialogLoading">
            <template #header>
                <h3>Change Password</h3>
                <p>Enter your new password below</p>
            </template>
            <template #body>
                <SMInput
                    control="password"
                    type="password"
                    label="New Password"
                    autofocus />
            </template>
            <template #footer-space-between>
                <SMButton label="Cancel" @click="handleClickCancel" />
                <SMButton type="submit" label="Update" />
            </template>
        </SMFormCard>
    </SMForm>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, reactive, ref } from "vue";
import { closeDialog } from "../SMDialog";
import { api } from "../../helpers/api";
import { Form, FormControl, FormObject } from "../../helpers/form";
import { And, Password, Required } from "../../helpers/validate";
import { useApplicationStore } from "../../store/ApplicationStore";
import { useToastStore } from "../../store/ToastStore";
import { useUserStore } from "../../store/UserStore";
import SMButton from "../SMButton.vue";
import SMFormCard from "../SMFormCard.vue";
import SMForm from "../SMForm.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMInput from "../SMInput.vue";

const form: FormObject = reactive(
    Form({
        password: FormControl("", And([Required(), Password()])),
    })
);

const applicationStore = useApplicationStore();
const userStore = useUserStore();
const dialogLoading = ref(false);

/**
 * User clicks cancel button to close dialog
 */
const handleClickCancel = () => {
    closeDialog(false);
};

/**
 * User clicks form submit button
 */
const handleSubmit = async () => {
    try {
        dialogLoading.value = true;

        await api.put({
            url: "/users/{id}",
            params: {
                id: userStore.id,
            },
            body: {
                password: form.controls.password.value,
            },
        });

        const toastStore = useToastStore();

        toastStore.addToast({
            title: "Password Reset",
            content: "Your password has been reset",
            type: "success",
        });
        closeDialog(false);
    } catch (error) {
        form.apiErrors(error);
    } finally {
        dialogLoading.value = false;
    }
};

/**
 * Handle a keyboard event in this component.
 *
 * @param {KeyboardEvent} event The keyboard event.
 * @returns {boolean} If the event was handled.
 */
const eventKeyUp = (event: KeyboardEvent): boolean => {
    if (event.key === "Escape") {
        handleClickCancel();
        return true;
    }

    return false;
};

onMounted(() => {
    applicationStore.addKeyUpListener(eventKeyUp);
});

onUnmounted(() => {
    applicationStore.removeKeyUpListener(eventKeyUp);
});
</script>
