<template>
    <SMModal>
        <SMDialog :loading="formLoading">
            <h1>Change Password</h1>
            <SMForm v-model="form">
                <SMMessage
                    v-if="isSuccessful"
                    type="success"
                    message="Your password has been changed successfully" />
                <SMInput
                    v-if="!isSuccessful"
                    control="password"
                    type="password"
                    label="New Password" />
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
            </SMForm>
        </SMDialog>
    </SMModal>
</template>

<script setup lang="ts">
import { api } from "../../helpers/api";
import { FormControl } from "../../helpers/form";
import { And, Required, Password } from "../../helpers/validate";
import { useUserStore } from "../../store/UserStore";
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import SMModal from "../SMModal.vue";
import SMDialog from "../SMDialog.vue";
import SMMessage from "../SMMessage.vue";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMInput from "../SMInput.vue";

const controlPassword = reactive(
    FormControl("", And([Required(), Password()]))
);
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
        const valid = controlPassword.validate();

        try {
            formLoading.value = true;
            await api.put({
                url: `/users/${userStore.id}`,
                body: {
                    password: controlPassword.value,
                },
            });

            isSuccessful.value = true;
        } catch (err) {
            controlPassword.error =
                err.json?.message || "An unexpected error occurred";
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
</script>
