<template>
    <SMMastHead
        title="Media"
        :back-link="{ name: 'dashboard' }"
        back-title="Return to Dashboard" />
    <SMContainer class="flex-grow-1">
        <SMToolbar>
            <SMButton
                :to="{ name: 'workshops' }"
                type="primary"
                label="Upload Media" />
            <SMInput
                v-model="itemSearch"
                label="Search"
                style="max-width: 350px"
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
                v-else
                :headers="headers"
                :items="items"
                @row-click="handleEdit">
                <template #item-size="item">
                    {{ bytesReadable(item.size) }}
                </template>
                <template #item-actions="item">
                    <SMButton
                        label="Edit"
                        :dropdown="{
                            download: 'Download',
                            delete: 'Delete',
                        }"
                        size="medium"
                        @click="handleActionButton(item, $event)"></SMButton>
                </template>
            </SMTable>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMButton from "../../components/SMButton.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import { api } from "../../helpers/api";
import { Media, MediaCollection } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { bytesReadable } from "../../helpers/types";
import { useToastStore } from "../../store/ToastStore";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMTable from "../../components/SMTable.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMNoItems from "../../components/SMNoItems.vue";
import SMLoading from "../../components/SMLoading.vue";
import { updateRouterParams } from "../../helpers/url";

const route = useRoute();
const router = useRouter();
const toastStore = useToastStore();

const items = ref([]);
const itemsLoading = ref(true);
const itemSearch = ref((route.query.search as string) || "");
const itemsTotal = ref(0);
const itemsPerPage = 25;
const itemsPage = ref(parseInt((route.query.page as string) || "1"));

const headers = [
    { text: "Name", value: "title", sortable: true },
    { text: "Size", value: "size", sortable: true },
    { text: "Uploaded By", value: "user.display_name", sortable: true },
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
 * @param {Media} item The media item.
 * @param {string} extra The option selected.
 * @param option
 */
const handleActionButton = (item: Media, option: string): void => {
    if (option.length == 0) {
        handleEdit(item);
    } else if (option.toLowerCase() == "download") {
        handleDownload(item);
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
            ] = `title:${itemSearch.value},OR,name:${itemSearch.value},OR,description:${itemSearch.value}`;
        }

        let result = await api.get({
            url: "/media",
            params: params,
        });

        const data = result.data as MediaCollection;
        data.media.forEach(async (row) => {
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.created_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
            if (row.updated_at !== "undefined") {
                row.updated_at = new SMDate(row.updated_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }

            items.value.push(row);
        });

        itemsTotal.value = data.total;
    } catch (error) {
        if (error.status != 404) {
            toastStore.addToast({
                title: "Server Error",
                content:
                    "An error occurred retrieving the list from the server.",
                type: "danger",
            });
        }
    } finally {
        itemsLoading.value = false;
    }
};

/**
 * User requests to edit the item
 *
 * @param {Media} item The media item.
 */
const handleEdit = (item: Media) => {
    router.push({ name: "dashboard-media-edit", params: { id: item.id } });
};

/**
 * Request to delete a media item from the server.
 *
 * @param {Media} item The media object to delete.
 */
const handleDelete = async (item: Media) => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete File?",
        text: `Are you sure you want to delete the file <strong>${item.title}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete File",
        },
    });

    if (result == true) {
        try {
            await api.delete({
                url: "/media/{id}",
                params: {
                    id: item.id,
                },
            });

            toastStore.addToast({
                title: "File Deleted",
                content: `The file ${item.title} has been deleted.`,
                type: "success",
            });
            handleLoad();
        } catch (error) {
            toastStore.addToast({
                title: "Error Deleting File",
                content:
                    error.data?.message ||
                    "An unexpected server error occurred",
                type: "danger",
            });
        }
    }
};

/**
 * Handle the user requesting to download the item.
 *
 * @param {Media} item The media item.
 */
const handleDownload = (item: Media) => {
    window.open(`${item.url}?download=1`, "_blank");
};

handleLoad();
</script>

<style lang="scss">
.page-dashboard-media-list {
    .table tr {
        td:first-of-type {
            word-break: break-all;
        }

        td:not(:first-of-type) {
            white-space: nowrap;
        }
    }
}
</style>
