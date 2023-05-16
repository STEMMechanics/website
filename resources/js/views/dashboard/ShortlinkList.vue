<template>
    <SMPage permission="admin/users">
        <SMMastHead
            title="Shortlinks"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <SMContainer class="flex-grow-1">
            <SMToolbar>
                <SMButton
                    :to="{ name: 'dashboard-shortlink-create' }"
                    type="primary"
                    label="Create Link" />
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
                <SMNoItems v-if="items.length == 0" text="No Links Found" />
                <SMTable
                    v-if="items.length > 0"
                    :headers="headers"
                    :items="items"
                    @row-click="handleEdit">
                    <template #item-actions="item">
                        <SMButton
                            label="Edit"
                            :dropdown="{
                                copy: 'Copy Shortlink',
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
import SMTable from "../../components/SMTable.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";
import SMNoItems from "../../components/SMNoItems.vue";
import SMButton from "../../components/SMButton.vue";
import SMInput from "../../components/SMInput.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import { updateRouterParams } from "../../helpers/url";
import { Shortlink, ShortlinkCollection } from "../../helpers/api.types";
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
    { text: "Code", value: "code", sortable: true },
    { text: "URL", value: "url", sortable: true },
    { text: "Used", value: "used", sortable: true },
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
 * @param {Shortlink} item The item.
 * @param option
 */
const handleActionButton = (item: Shortlink, option: string): void => {
    if (option.length == 0) {
        handleEdit(item);
    } else if (option.toLowerCase() == "copy") {
        handleCopy(item);
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
            ] = `code:${itemSearch.value},OR,url:${itemSearch.value}`;
        }

        let result = await api.get({
            url: "/shortlinks",
            params: params,
        });

        const collection = getApiResultData<ShortlinkCollection>(result);
        items.value = collection.shortlinks;
        itemsTotal.value = collection.total;
    } catch (err) {
        /* empty */
    }

    itemsLoading.value = false;
};

const handleEdit = (shortlink: Shortlink) => {
    router.push({
        name: "dashboard-shortlink-edit",
        params: { id: shortlink.id },
    });
};

const handleCopy = (shortlink: Shortlink) => {
    navigator.clipboard
        .writeText(`https://stemmech.com.au/${shortlink.code}`)
        .then(() => {
            useToastStore().addToast({
                title: "Copied to Clipboard",
                content: "The shortlink URL has been copied to the clipboard.",
                type: "success",
            });
        })
        .catch(() => {
            useToastStore().addToast({
                title: "Copy to Clipboard",
                content: "Failed to copy the shortlink URL to the clipboard.",
                type: "danger",
            });
        });
};

const handleDelete = async (shortlink: Shortlink) => {
    let result = await openDialog(DialogConfirm, {
        title: "Delete User?",
        text: `Are you sure you want to delete the user <strong>${shortlink.code}</strong>?`,
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
            await api.delete(`shortlinks${shortlink.id}`);
            handleLoad();

            useToastStore().addToast({
                title: "Shortlink Deleted",
                content: "Shortlink deleted successfully.",
                type: "success",
            });
        } catch (err) {
            useToastStore().addToast({
                title: "Server Error",
                content:
                    "Shortlink could not be deleted because an error occurred.",
                type: "danger",
            });
        }
    }
};

handleLoad();
</script>

<style lang="scss">
.page-dashboard-shortlink-list {
    .toolbar-search {
        max-width: 350px;
    }
}

@media only screen and (max-width: 768px) {
    .page-dashboard-shortlink-list {
        .toolbar-search {
            max-width: none;
        }
    }
}
</style>
