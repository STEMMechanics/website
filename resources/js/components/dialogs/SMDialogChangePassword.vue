<template>
    <div
        class="fixed top-0 left-0 w-full h-full bg-black bg-op-20 backdrop-blur"></div>
    <div class="fixed top-0 left-0 w-full flex-justify-center flex pt-36">
        <div
            class="max-w-2xl mx-auto border-1 bg-white rounded-xl mt-7xl text-gray-5 px-12 py-8">
            <SMForm :model-value="form" @submit="handleSubmit">
                <h3 class="mb-2">Change Password</h3>
                <p class="mb-4">Enter your new password below</p>
                <SMInput
                    control="password"
                    type="password"
                    label="New Password"
                    autofocus />
                <div class="flex flex-justify-between pt-4">
                    <button
                        class="font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        type="button"
                        @click="handleClickCancel">
                        Cancel
                    </button>
                    <input
                        class="font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        role="button"
                        type="submit"
                        value="Update" />
                </div>
            </SMForm>
        </div>
    </div>
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
import SMForm from "../SMForm.vue";
import SMInput from "../SMInput.vue";

const form: FormObject = reactive(
    Form({
        password: FormControl("", And([Required(), Password()])),
    }),
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
        form.apiErrors(error, (message) => {
            useToastStore().addToast({
                title: "An error occurred",
                content: message,
                type: "danger",
            });
        });
    } finally {
        dialogLoading.value = false;
    }
};

/**
 * Handle a keyboard event in this component.
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
