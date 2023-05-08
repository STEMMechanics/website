<template>
    <SMPage permission="admin/users">
        <SMMastHead
            title="Users"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <SMContainer class="flex-grow-1">
            <SMToolbar>
                <SMButton
                    :to="{ name: 'dashboard-user-create' }"
                    type="primary"
                    label="Create User" />
                <SMInput
                    v-model="itemSearch"
                    label="Search"
                    class="toolbar-search"
                    @keyup.enter="handleSearch">
                    <template #append>
                        <SMButton
                            type="primary"
                            label="Search"
                            icon="search-outline"
                            @click="handleSearch" />
                    </template>
                </SMInput>
            </SMToolbar>
            <SMLoading large v-if="itemsLoading" />
            <template v-else>
                <SMPagination
                    v-if="items.length < itemsTotal"
                    v-model="itemsPage"
                    :total="itemsTotal"
                    :per-page="itemsPerPage" />
                <SMNoItems v-if="items.length == 0" text="No Media Found" />
                <SMTable
                    :headers="headers"
                    :items="items"
                    @row-click="handleEdit">
                    <template #item-actions="item">
                        <SMButton
                            label="Edit"
                            :dropdown="{
                                delete: 'Delete',
                            }"
                            size="medium"
                            @click="handleActionButton(item, $event)" />
                    </template>
                </SMTable>
            </template>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { api, getApiResultData } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import SMTable from "../../components/SMTable.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";
import SMNoItems from "../../components/SMNoItems.vue";
import SMButton from "../../components/SMButton.vue";
import SMInput from "../../components/SMInput.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import { updateRouterParams } from "../../helpers/url";
import { User, UserCollection } from "../../helpers/api.types";
import SMLoading from "../../components/SMLoading.vue";
import SMPagination from "../../components/SMPagination.vue";

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
 * Handle user selecting option in action button.
 *
 * @param {Event} item The event item.
 * @param option
 */
const handleActionButton = (item: Event, option: string): void => {
    if (option.length == 0) {
        handleEdit(item);
    } else if (option.toLowerCase() == "delete") {
        handleDelete(item);
    }
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
    router.push({ name: "dashboard-user-edit", params: { id: user.id } });
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
            await api.delete(`users${user.id}`);
            handleLoad();

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

<style lang="scss">
.page-dashboard-user-list {
    .toolbar-search {
        max-width: 350px;
    }

    // .table tr {
    //     td:first-of-type,
    //     td:nth-of-type(2) {
    //         word-break: break-all;
    //     }

    //     td:not(:first-of-type) {
    //         white-space: nowrap;
    //     }
    // }
}

@media only screen and (max-width: 768px) {
    .page-dashboard-user-list {
        .toolbar-search {
            max-width: none;
        }
    }
}
</style>
