<template>
    <SMPageStatus v-if="!userHasPermission('admin/users')" :status="403" />
    <template v-else>
        <SMMastHead
            title="Users"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <div class="max-w-7xl mx-auto mt-8 px-4">
            <div class="flex items-center flex-justify-between mb-8">
                <router-link
                    role="button"
                    :to="{ name: 'dashboard-user-create' }"
                    class="font-medium px-6 py-3.1 rounded-md hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    >Create User</router-link
                >
                <SMInput
                    v-model="itemSearch"
                    label="Search"
                    class="max-w-xl ml-4"
                    @keyup.enter="handleSearch">
                    <template #append>
                        <button
                            type="button"
                            class="font-medium px-4 py-3.1 rounded-r-2 hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                            @click="handleSearch">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M796-121 533-384q-30 26-69.959 40.5T378-329q-108.162 0-183.081-75Q120-479 120-585t75-181q75-75 181.5-75t181 75Q632-691 632-584.85 632-542 618-502q-14 40-42 75l264 262-44 44ZM377-389q81.25 0 138.125-57.5T572-585q0-81-56.875-138.5T377-781q-82.083 0-139.542 57.5Q180-666 180-585t57.458 138.5Q294.917-389 377-389Z"
                                    fill="currentColor" />
                            </svg>
                        </button>
                    </template>
                </SMInput>
            </div>
            <SMLoading v-if="itemsLoading" />
            <div
                v-else-if="!itemsLoading && items.length == 0"
                class="py-12 text-center">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 -960 960 960"
                    class="h-24 text-gray-5">
                    <path
                        d="M453-280h60v-240h-60v240Zm26.982-314q14.018 0 23.518-9.2T513-626q0-14.45-9.482-24.225-9.483-9.775-23.5-9.775-14.018 0-23.518 9.775T447-626q0 13.6 9.482 22.8 9.483 9.2 23.5 9.2Zm.284 514q-82.734 0-155.5-31.5t-127.266-86q-54.5-54.5-86-127.341Q80-397.681 80-480.5q0-82.819 31.5-155.659Q143-709 197.5-763t127.341-85.5Q397.681-880 480.5-880q82.819 0 155.659 31.5Q709-817 763-763t85.5 127Q880-563 880-480.266q0 82.734-31.5 155.5T763-197.684q-54 54.316-127 86Q563-80 480.266-80Zm.234-60Q622-140 721-239.5t99-241Q820-622 721.188-721 622.375-820 480-820q-141 0-240.5 98.812Q140-622.375 140-480q0 141 99.5 240.5t241 99.5Zm-.5-340Z"
                        fill="currentColor" />
                </svg>
                <p class="text-lg text-gray-5">
                    {{ "No users where found" }}
                </p>
            </div>
            <template v-else>
                <SMPagination
                    v-if="items.length < itemsTotal"
                    v-model="itemsPage"
                    :total="itemsTotal"
                    :per-page="itemsPerPage" />
                <SMTable :headers="headers" :items="items">
                    <template #item-actions="item">
                        <button
                            type="button"
                            class="bg-transparent cursor-pointer hover:text-sky-5"
                            title="Edit"
                            @click="handleEdit(item)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M180-180h44l443-443-44-44-443 443v44Zm614-486L666-794l42-42q17-17 42-17t42 17l44 44q17 17 17 42t-17 42l-42 42Zm-42 42L248-120H120v-128l504-504 128 128Zm-107-21-22-22 44 44-22-22Z"
                                    fill="currentColor" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="bg-transparent cursor-pointer hover:text-red-7"
                            title="Delete"
                            @click="handleDelete(item)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M261-120q-24.75 0-42.375-17.625T201-180v-570h-41v-60h188v-30h264v30h188v60h-41v570q0 24-18 42t-42 18H261Zm438-630H261v570h438v-570ZM367-266h60v-399h-60v399Zm166 0h60v-399h-60v399ZM261-750v570-570Z"
                                    fill="currentColor" />
                            </svg>
                        </button>
                    </template>
                </SMTable>
            </template>
        </div>
    </template>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { api, getApiResultData } from "../../helpers/api";
import { userHasPermission } from "../../helpers/utils";
import { SMDate } from "../../helpers/datetime";
import SMTable from "../../components/SMTable.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";
import SMInput from "../../components/SMInput.vue";
import { updateRouterParams } from "../../helpers/url";
import { User, UserCollection } from "../../helpers/api.types";
import SMLoading from "../../components/SMLoading.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMPageStatus from "../../components/SMPageStatus.vue";

const route = useRoute();
const router = useRouter();

const items = ref([]);
const itemsLoading = ref(false);
const itemSearch = ref((route.query.search as string) || "");
const itemsTotal = ref(0);
const itemsPerPage = 25;
const itemsPage = ref(parseInt((route.query.page as string) || "1"));

const headers = [
    { text: "Display name", value: "display_name", sortable: true },
    { text: "First name", value: "first_name", sortable: true },
    { text: "Last name", value: "last_name", sortable: true },
    { text: "Email", value: "email", sortable: true },
    { text: "Actions", value: "actions" },
];

/**
 * Watch if page number changes.
 */
watch(itemsPage, () => {
    handleLoad();
});

/**
 * Handle searching for item.
 */
const handleSearch = () => {
    itemsPage.value = 1;
    handleLoad();
};

/**
 * Handle loading the page and list
 */
const handleLoad = async () => {
    itemsLoading.value = true;
    items.value = [];
    itemsTotal.value = 0;

    updateRouterParams(router, {
        search: itemSearch.value,
        page: itemsPage.value == 1 ? "" : itemsPage.value.toString(),
    });

    try {
        let params = {
            page: itemsPage.value,
            limit: itemsPerPage,
        };

        if (itemSearch.value.length > 0) {
            params[
                "filter"
            ] = `title:${itemSearch.value},OR,content:${itemSearch.value}`;
        }

        let result = await api.get({
            url: "/users",
            params: params,
        });

        const userCollection = getApiResultData<UserCollection>(result);
        items.value = userCollection.users;

        items.value.forEach((row) => {
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.created_at, {
                    format: "yMd",
                    utc: true,
                }).relative();
            }
        });

        itemsTotal.value = userCollection.total;
    } catch (err) {
        /* empty */
    }

    itemsLoading.value = false;
};

const handleEdit = (user: User) => {
    router.push({
        name: "dashboard-user-edit",
        params: { id: user.id },
        query: {
            return: encodeURIComponent(
                window.location.pathname + window.location.search
            ),
        },
    });
};

const handleDelete = async (user: User) => {
    let result = await openDialog(DialogConfirm, {
        title: "Delete User?",
        text: `Are you sure you want to delete the user <strong>${user.display_name}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete User",
        },
    });

    if (result == true) {
        try {
            // await api.delete(`users${user.id}`);
            // handleLoad();

            useToastStore().addToast({
                title: "User Deleted",
                content: "User deleted successfully.",
                type: "success",
            });
        } catch (err) {
            useToastStore().addToast({
                title: "Server Error",
                content: "User could not be deleted because an error occurred.",
                type: "danger",
            });
        }
    }
};

handleLoad();
</script>
