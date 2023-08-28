<template>
    <SMPageStatus v-if="!userHasPermission('admin/media')" :status="403" />
    <template v-else>
        <SMMastHead
            title="Media"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <div class="max-w-7xl mx-auto mt-8 px-8">
            <div
                class="flex flex-col md:flex-row gap-4 items-center flex-justify-between mb-4">
                <router-link
                    role="button"
                    :to="{ name: 'dashboard-media-create' }"
                    class="font-medium w-full md:w-auto text-center px-6 py-3.1 rounded-md hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    >Upload Media</router-link
                >
                <SMInput
                    v-model="itemSearch"
                    label="Search"
                    class="w-full md:max-w-xl"
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
            <SMLoading large v-if="itemsLoading" />
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
                    {{ "No media where found" }}
                </p>
            </div>
            <template v-else>
                <SMPagination
                    v-if="items.length < itemsTotal"
                    class="mb-4"
                    v-model="itemsPage"
                    :total="itemsTotal"
                    :per-page="itemsPerPage" />
                <SMTable
                    :headers="headers"
                    :items="items"
                    class="sm-table-media mb-4">
                    <template #item-select="item">
                        <SMCheckbox
                            v-model="itemsSelected[item.id]"
                            @click.stop />
                    </template>
                    <template #item-size="item">
                        {{ bytesReadable(item.size) }}
                    </template>
                    <template #item-title="item">
                        <div class="flex gap-2">
                            <div
                                class="w-100 h-100 max-h-15 max-w-20 mr-2 bg-contain bg-no-repeat bg-center"
                                :style="{
                                    backgroundImage: `url('${mediaGetThumbnail(
                                        item,
                                    )}')`,
                                }"></div>
                            <div class="flex flex-col flex-justify-center">
                                <span>{{ item.title }}</span>
                                <span class="small">({{ item.name }})</span>
                            </div>
                        </div>
                    </template>
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
                            class="bg-transparent cursor-pointer hover:text-sky-5"
                            title="Download"
                            @click="handleDownload(item)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M220-160q-24 0-42-18t-18-42v-143h60v143h520v-143h60v143q0 24-18 42t-42 18H220Zm260-153L287-506l43-43 120 120v-371h60v371l120-120 43 43-193 193Z"
                                    fill="currrentColor" />
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
                <div class="flex flex-justify-start gap-4 flex-items-center">
                    <button
                        type="button"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer disabled:bg-gray-3 disabled:text-white disabled:cursor-not-allowed disabled:hover:shadow-none"
                        :disabled="computedSelectedCount == 0"
                        @click="handleEditSelected">
                        Edit Selected
                    </button>
                    <button
                        type="button"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-red-600 hover:bg-red-500 text-white cursor-pointer disabled:bg-gray-3 disabled:text-white disabled:cursor-not-allowed disabled:hover:shadow-none"
                        :disabled="computedSelectedCount == 0"
                        @click="handleDeleteSelected">
                        Delete Selected
                    </button>
                    <div class="small">
                        {{ computedSelectedCount }} selected
                    </div>
                </div>
            </template>
        </div>
    </template>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { api } from "../../helpers/api";
import { Media, MediaCollection } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { bytesReadable } from "../../helpers/types";
import { useToastStore } from "../../store/ToastStore";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMTable from "../../components/SMTable.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMLoading from "../../components/SMLoading.vue";
import { updateRouterParams } from "../../helpers/url";
import { userHasPermission } from "../../helpers/utils";
import SMPageStatus from "../../components/SMPageStatus.vue";
import SMCheckbox from "../../components/SMCheckbox.vue";
import { mediaGetThumbnail } from "../../helpers/media";

const route = useRoute();
const router = useRouter();
const toastStore = useToastStore();

const items = ref([]);
const itemsLoading = ref(true);
const itemSearch = ref((route.query.search as string) || "");
const itemsTotal = ref(0);
const itemsPerPage = 25;
const itemsPage = ref(parseInt((route.query.page as string) || "1"));
const itemsSelected = ref({});

const headers = [
    { text: "", value: "select", sortable: false },
    { text: "Title (Name)", value: "title", sortable: true },
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

            if (
                Object.prototype.hasOwnProperty.call(
                    itemsSelected.value,
                    row.id,
                ) == false
            ) {
                itemsSelected.value[row.id] = false;
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

const handleSelect = (item: Media) => {
    if (Object.prototype.hasOwnProperty.call(itemsSelected.value, item.id)) {
        itemsSelected.value[item.id] = !itemsSelected.value[item.id];
    } else {
        itemsSelected.value[item.id] = true;
    }
};

/**
 * User requests to edit the item
 * @param {Media} item The media item.
 */
const handleEdit = (item: Media) => {
    router.push({
        name: "dashboard-media-edit",
        params: { id: item.id },
        query: {
            return: encodeURIComponent(
                window.location.pathname + window.location.search,
            ),
        },
    });
};

/**
 * Request to delete a media item from the server.
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
 * Request to delete selected media item from the server.
 */
const handleDeleteSelected = async () => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete Files?",
        text: `Are you sure you want to delete the <strong>${computedSelectedCount.value}</strong> selected files?`,
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
        let errorCount = 0;
        let successCount = 0;

        const deleteItems = Object.entries(itemsSelected.value).filter(
            ([key, value]) => value === true,
        );

        await Promise.all(
            deleteItems.map(async ([key, value]) => {
                // Perform actions for each item that is true

                // Perform asynchronous operation
                try {
                    await api.delete({
                        url: "/media/{id}",
                        params: {
                            id: key,
                        },
                    });

                    successCount++;
                } catch (error) {
                    errorCount++;
                }
            }),
        );

        if (errorCount === 0) {
            toastStore.addToast({
                title: "Files Deleted",
                content: `The selected files have been deleted.`,
                type: "success",
            });
        } else if (successCount === 0) {
            toastStore.addToast({
                title: "Error Deleting Files",
                content: "An unexpected server error occurred.",
                type: "danger",
            });
        } else {
            toastStore.addToast({
                title: "Some Files Deleted",
                content: `Only ${successCount} files where deleted. ${errorCount} could not because of an unexpected error.`,
                type: "warning",
            });
        }

        handleLoad();
    }
};

/**
 * Request to edit selected media item from the server.
 */
const handleEditSelected = async () => {
    const editItems = Object.entries(itemsSelected.value)
        .filter(([key, value]) => value === true)
        .map(([key, value]) => key)
        .join(",");

    router.push({
        name: "dashboard-media-edit",
        params: { id: editItems },
        query: {
            return: encodeURIComponent(
                window.location.pathname + window.location.search,
            ),
        },
    });
};

/**
 * Handle the user requesting to download the item.
 * @param {Media} item The media item.
 */
const handleDownload = (item: Media) => {
    window.open(`${item.url}?download=1`, "_blank");
};

const computedSelectedCount = computed(() => {
    const selectedValues = Object.values(itemsSelected.value);
    const trueValues = selectedValues.filter((value) => value === true);
    return trueValues.length;
});

handleLoad();
</script>

<style lang="scss">
.sm-table-media {
    tbody tr td:last-child {
        white-space: nowrap;
    }
}
</style>
