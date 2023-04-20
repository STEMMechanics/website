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
                v-model="search"
                label="Search"
                style="max-width: 350px"
                @keyup.enter="handleClickSearch">
                <template #append>
                    <SMButton
                        type="primary"
                        label="Search"
                        icon="search-outline"
                        @click="handleClickSearch" />
                </template>
            </SMInput>
        </SMToolbar>
        <SMLoading large v-if="pageLoading" />
        <template v-else>
            <SMPagination
                v-if="items.length < totalFound"
                v-model="page"
                :total="totalFound"
                :per-page="perPage" />
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
                        @click="handleClick(item, $event)"></SMButton>
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
import { Media } from "../../helpers/api.types";
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
const pageLoading = ref(true);
const search = ref(route.query.search || "");
const items = ref([]);
const totalFound = ref(0);
const perPage = 25;
const page = ref(parseInt((route.query.page as string) || "1"));

const headers = [
    { text: "Name", value: "title", sortable: true },
    { text: "Size", value: "size", sortable: true },
    { text: "Uploaded By", value: "user.display_name", sortable: true },
    { text: "Actions", value: "actions" },
];

const handleClickSearch = () => {
    page.value = 1;
    handleLoad();
};

const handleClick = (item, extra: string): void => {
    if (extra.length == 0) {
        handleEdit(item);
    } else if (extra.toLowerCase() == "download") {
        handleDownload(item);
    } else if (extra.toLowerCase() == "delete") {
        handleDelete(item);
    }
};

/**
 * Watch if page number changes.
 */
watch(page, () => {
    handleLoad();
});

const handleLoad = async () => {
    pageLoading.value = true;
    items.value = [];
    totalFound.value = 0;

    let routerParams = {
        search: search.value as string,
        page: page.value == 1 ? "" : page.value.toString(),
    };
    updateRouterParams(router, routerParams);

    try {
        let params = {
            page: page.value,
            limit: perPage,
        };

        if (search.value.length > 0) {
            params[
                "filter"
            ] = `title:${search.value},OR,name:${search.value},OR,description:${search.value}`;
        }

        let res = await api.get({
            url: "/media",
            params: params,
        });
        if (!res.data.media) {
            throw new Error("The server is currently not available");
        }

        items.value = [];

        res.data.media.forEach(async (row) => {
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

        totalFound.value = res.data.total;
    } catch (error) {
        if (error.status != 404) {
            toastStore.addToast({
                title: "Server Error",
                content: error.message,
                //"An error occurred retrieving the list from the server.",
                type: "danger",
            });
        }
    } finally {
        pageLoading.value = false;
    }
};

handleLoad();

const handleEdit = (item) => {
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

    if (result) {
        try {
            let r = await api.delete({
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

const handleDownload = (item) => {
    window.open(`${item.url}?download=1`, "_blank");
};
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
