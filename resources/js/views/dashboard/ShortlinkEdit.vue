<template>
    <SMMastHead
        :title="pageHeading"
        :back-link="
            route.params.id || isCreating
                ? { name: 'dashboard-shortlink-list' }
                : { name: 'dashboard' }
        "
        :back-title="
            route.params.id || isCreating
                ? 'Back to Shortlinks'
                : 'Back to Dashboard'
        " />
    <SMContainer>
        <SMForm :model-value="form" @submit="handleSubmit">
            <SMRow>
                <SMColumn><SMInput control="code" /></SMColumn>
                <SMColumn
                    ><SMInput type="static" v-model="used" label="Times used"
                /></SMColumn>
            </SMRow>
            <SMRow>
                <SMColumn><SMInput control="url" /></SMColumn>
            </SMRow>
            <SMRow>
                <SMColumn>
                    <input
                        role="button"
                        type="submit"
                        :value="saveButtonLabel" />
                </SMColumn>
            </SMRow>
        </SMForm>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { ShortlinkResponse } from "../../helpers/api.types";
import { Form, FormControl } from "../../helpers/form";
import { And, Length, Max, Min, Required } from "../../helpers/validate";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";

const route = useRoute();
const router = useRouter();

const isCreating = route.path.endsWith("/create");

let form = reactive(
    Form({
        code: FormControl("", And([Required(), Length(4)])),
        url: FormControl("", And([Required(), Min(4), Max(255)])),
    })
);

const used = ref(0);

/**
 * Load the page data.
 */
const loadData = async () => {
    if (route.params.id) {
        try {
            form.loading(true);
            const result = await api.get({
                url: "/shortlinks/{id}",
                params: {
                    id: route.params.id,
                },
            });

            const data = result.data as ShortlinkResponse;

            if (data && data.shortlink) {
                form.controls.code.value = data.shortlink.code;
                form.controls.url.value = data.shortlink.url;
                used.value = data.shortlink.used;
            }
        } catch (error) {
            form.apiErrors(error, (message) => {
                useToastStore().addToast({
                    title: "An error occurred",
                    content: message,
                    type: "danger",
                });
            });
        } finally {
            form.loading(false);
        }
    } else {
        let foundCode = false;

        while (foundCode == false) {
            const randomCode = Math.random()
                .toString(36)
                .substring(2, 6)
                .toLowerCase();

            try {
                await api.get({
                    url: "/shortlinks",
                    params: {
                        code: randomCode,
                    },
                });
            } catch (err) {
                foundCode = true;
                if (err.status == 404) {
                    form.controls.code.value = randomCode;
                }
            }
        }
    }
};

/**
 * Handle the user submitting the form.
 */
const handleSubmit = async () => {
    try {
        form.loading(true);
        if (isCreating == false) {
            await api.put({
                url: "/shortlinks/{id}",
                params: {
                    id: route.params.id,
                },
                body: {
                    code: form.controls.code.value,
                    url: form.controls.url.value,
                },
            });

            useToastStore().addToast({
                title: "Shortlink Updated",
                content: "The shortlink has been updated.",
                type: "success",
            });
        } else {
            await api.post({
                url: "/shortlinks",
                body: {
                    code: form.controls.code.value,
                    url: form.controls.url.value,
                },
            });

            useToastStore().addToast({
                title: "Shortlink Created",
                content: "The shortlink has been created.",
                type: "success",
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get("return");
        if (returnUrl) {
            router.push(decodeURIComponent(returnUrl));
        } else {
            router.push({ name: "dashboard-shortlink-list" });
        }
    } catch (error) {
        form.apiErrors(error, (message) => {
            useToastStore().addToast({
                title: "An error occurred",
                content: message,
                type: "danger",
            });
        });
    } finally {
        form.loading(false);
    }
};

const pageHeading = computed(() => {
    return route.params.id == null ? "Create Shortlink" : "Edit Shortlink";
});

const saveButtonLabel = computed(() => {
    return route.params.id == null ? "Create" : "Update";
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
