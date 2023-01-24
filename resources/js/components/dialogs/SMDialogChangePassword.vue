<template>
    <SMModal>
        <SMDialog :loading="formLoading">
            <h1>Change Password</h1>
            <SMMessage
                v-if="isSuccessful"
                type="success"
                message="Your password has been changed successfully" />
            <SMInput
                v-if="!isSuccessful"
                v-model="formData.password.value"
                type="password"
                label="New Password"
                required
                :error="formData.password.error"
                @blur="fieldValidate(formData.password)" />
            <SMFormFooter>
                <template v-if="!isSuccessful" #left>
                    <SMButton
                        type="secondary"
                        label="Cancel"
                        @click="handleCancel()" />
                </template>
                <template #right>
                    <SMButton
                        type="primary"
                        :label="btnConfirm"
                        @click="handleConfirm()" />
                </template>
            </SMFormFooter>
        </SMDialog>
    </SMModal>
</template>

<script setup lang="ts">
import axios from "axios";
import { useUserStore } from "../../store/UserStore";
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import SMModal from "../SMModal.vue";
import SMDialog from "../SMDialog.vue";
import SMMessage from "../SMMessage.vue";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMInput from "../SMInput.vue";
import {
    useValidation,
    isValidated,
    fieldValidate,
} from "../../helpers/validation";

const formData = reactive({
    password: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A password is needed",
            min: 8,
            min_message: "Your password needs to be at least %d characters",
            password: "special",
        },
    },
});

const userStore = useUserStore();
const formLoading = ref(false);
const isSuccessful = ref(false);

const btnConfirm = computed(() => {
    return isSuccessful.value ? "Close" : "Update";
});

const handleCancel = () => {
    closeDialog(false);
};

const handleConfirm = async () => {
    if (isSuccessful.value == true) {
        closeDialog(true);
    } else {
        if (isValidated(formData)) {
            try {
                formLoading.value = true;
                await axios.put(`users/${userStore.id}`, {
                    password: formData.password.value,
                });

                isSuccessful.value = true;
            } catch (err) {
                formData.password.error =
                    err.response?.data?.message ||
                    "An unexpected error occurred";
            }
        }
    }

    formLoading.value = false;
};

const eventKeyUp = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        handleCancel();
    } else if (event.key === "Enter") {
        handleConfirm();
    }
};

onMounted(() => {
    document.addEventListener("keyup", eventKeyUp);
});

onUnmounted(() => {
    document.removeEventListener("keyup", eventKeyUp);
});

useValidation(formData);
</script>
