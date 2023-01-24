<template>
    <SMContainer>
        <SMRow>
            <SMDialog :loading="formLoading">
                <SMHeading :heading="pageHeading" />
                <SMMessage
                    v-if="formMessage.message"
                    :icon="formMessage.icon"
                    :type="formMessage.type"
                    :message="formMessage.message" />
                <form @submit.prevent="submit">
                    <SMRow>
                        <SMColumn
                            ><SMInput
                                v-model="formData.first_name.value"
                                label="First Name"
                                required
                                :error="formData.first_name.error"
                                @blur="fieldValidate(formData.first_name)"
                        /></SMColumn>
                        <SMColumn
                            ><SMInput
                                v-model="formData.last_name.value"
                                label="Last Name"
                                required
                                :error="formData.last_name.error"
                                @blur="fieldValidate(formData.last_name)"
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn
                            ><SMInput
                                v-model="formData.email.value"
                                label="Email"
                                required
                                :error="formData.email.error"
                                @blur="fieldValidate(formData.email)"
                        /></SMColumn>
                        <SMColumn
                            ><SMInput
                                v-model="formData.phone.value"
                                label="Phone"
                                :error="formData.phone.error"
                                @blur="fieldValidate(formData.phone)"
                        /></SMColumn>
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
                </form>
            </SMDialog>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../../helpers/validation";
import { useUserStore } from "../../store/UserStore";
import { useRoute } from "vue-router";
import { openDialog } from "vue3-promise-dialog";
import SMDialogChangePassword from "../../components/dialogs/SMDialogChangePassword.vue";

let formLoading = ref(false);
const route = useRoute();
const userStore = useUserStore();

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});
const formData = reactive({
    first_name: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A first name is needed",
            min: 2,
            min_message: "Your first name should be at least 2 letters long",
        },
    },
    last_name: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A last name is needed",
            min: 2,
            min_message: "Your last name should be at least 2 letters long",
        },
    },
    email: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A email address is needed",
            email: true,
            email_message: "Your email address is not correct",
        },
    },
    phone: {
        value: "",
        error: "",
        rules: {
            phone: true,
            phone_message: "Your phone number does not look correct",
        },
    },
});

useValidation(formData);

const loadData = async () => {
    if (route.params.id) {
        try {
            formLoading.value = true;
            let res = await axios.get(`users/${route.params.id}`);

            formData.first_name.value = res.data.user.first_name;
            formData.last_name.value = res.data.user.last_name;
            formData.phone.value = res.data.user.phone;
            formData.email.value = res.data.user.email;
            console.log(res);
        } catch (err) {
            formMessage.icon = "";
            formMessage.type = "error";
            formMessage.message = "";
            restParseErrors(formData, [formMessage, "message"], err);
        }
    } else {
        formData.first_name.value = userStore.firstName;
        formData.last_name.value = userStore.lastName;
        formData.phone.value = userStore.phone;
        formData.email.value = userStore.email;
    }

    formLoading.value = false;
};

const submit = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        if (isValidated(formData)) {
            formLoading.value = true;
            let res = await axios.put(`users/${userStore.id}`, {
                first_name: formData.first_name.value,
                last_name: formData.last_name.value,
                email: formData.email.value,
                phone: formData.phone.value,
            });

            userStore.setUserDetails(res.data.user);

            formMessage.type = "success";
            formMessage.icon = "";
            formMessage.message = "Your details have been updated";
        }
    } catch (err) {
        restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
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
