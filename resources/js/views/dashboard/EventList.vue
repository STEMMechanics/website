<template>
    <SMPageStatus v-if="!userHasPermission('admin/events')" :status="403" />
    <template v-else>
        <SMMastHead
            title="Events"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <div class="max-w-7xl mx-auto mt-8 p-4">
            <div
                class="flex flex-col md:flex-row gap-4 items-center flex-justify-between mb-4">
                <router-link
                    role="button"
                    :to="{ name: 'dashboard-event-create' }"
                    class="font-medium w-full md:w-auto text-center px-6 py-3.1 rounded-md hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    >Create Event</router-link
                >
                <SMInput
                    v-model="itemSearch"
                    label="Search"
                    class="max-w-xl"
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
                    {{ "No events where found" }}
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
                    class="sm-table-events"
                    :headers="headers"
                    :items="items">
                    <template #item-start_at="item">{{
                        formattedDate(item.start_at)
                    }}</template>
                    <template #item-location="item"
                        >{{ parseEventLocation(item) }}
                    </template>
                    <template #item-status="item"
                        >{{ toTitleCase(item.status) }}
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
                            title="View"
                            @click="handleView(item)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M180-120q-24.75 0-42.375-17.625T120-180v-600q0-24.75 17.625-42.375T180-840h600q24.75 0 42.375 17.625T840-780v600q0 24.75-17.625 42.375T780-120H180Zm0-60h600v-520H180v520Zm300.041-105Q400-285 337-328.152q-63-43.151-92-112Q274-509 336.959-552t143-43Q560-595 623-551.849q63 43.152 92 112.001Q686-371 623.041-328t-143 43ZM480-335q57 0 104.949-27.825T660-440q-27.102-49.35-75.051-77.175Q537-545 480-545t-104.949 27.825Q327.102-489.35 300-440q27.102 49.35 75.051 77.175Q423-335 480-335Zm0-105Zm.118 50Q501-390 515.5-404.618q14.5-14.617 14.5-35.5Q530-461 515.382-475.5q-14.617-14.5-35.5-14.5Q459-490 444.5-475.382q-14.5 14.617-14.5 35.5Q430-419 444.618-404.5q14.617 14.5 35.5 14.5Z"
                                    fill="currentColor" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="bg-transparent cursor-pointer hover:text-sky-5"
                            title="Duplicate"
                            @click="handleDuplicate(item)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                class="h-6">
                                <path
                                    d="M180-81q-24 0-42-18t-18-42v-603h60v603h474v60H180Zm120-120q-24 0-42-18t-18-42v-560q0-24 18-42t42-18h440q24 0 42 18t18 42v560q0 24-18 42t-42 18H300Zm0-60h440v-560H300v560Zm0 0v-560 560Z"
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
import { api } from "../../helpers/api";
import { EventCollection, Event, EventResponse } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { updateRouterParams } from "../../helpers/url";
import { useToastStore } from "../../store/ToastStore";
import { toTitleCase } from "../../helpers/string";
import { userHasPermission } from "../../helpers/utils";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMInput from "../../components/SMInput.vue";
import SMLoading from "../../components/SMLoading.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMTable from "../../components/SMTable.vue";
import SMPageStatus from "../../components/SMPageStatus.vue";

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
    { text: "Title", value: "title", sortable: true },
    { text: "Starts", value: "start_at", sortable: true },
    { text: "Status", value: "status", sortable: true },
    { text: "Location", value: "location", sortable: true },
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
            url: "/events",
            params: params,
        });

        const data = result.data as EventCollection;
        data.events.forEach(async (row) => {
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

const formattedDate = (d: string): string => {
    return new SMDate(d, {
        format: "ymd",
        utc: true,
    }).format("MMM d yyyy, h:mm aa");
};

/**
 * Handle viewing an event.
 * @param item
 */
const handleView = (item: Event): void => {
    //  router.push({ name: "event", params: { id: item.id } });
    window.open(
        router.resolve({ name: "event", params: { id: item.id } }).href,
        "_blank",
    );
};

/**
 * Handle duplicating an event.
 * @param item
 */
const handleDuplicate = async (item: Event): Promise<void> => {
    try {
        let data = {
            title: `Copy of ${item.title}`,
            location: item.location,
            location_url: item.location_url,
            address: item.address,
            start_at: item.start_at,
            end_at: item.end_at,
            status: "draft",
            publish_at: item.publish_at,
            registration_type: item.registration_type,
            registration_data: item.registration_data,
            content: item.content,
            hero: item.hero.id,
            price: item.price,
            ages: item.ages,
            attachments: item.attachments.map((item) => item.id).join(","),
        };

        let result = await api.post({
            url: "/events",
            body: data,
        });

        let event = result.data as EventResponse;
        useToastStore().addToast({
            title: "Event Duplicated",
            content: "The event has been duplicated.",
            type: "success",
        });

        router.push({
            name: "dashboard-event-edit",
            params: { id: event.event.id },
            query: {
                return: encodeURIComponent(
                    window.location.pathname + window.location.search,
                ),
            },
        });
    } catch (error) {
        console.log(error);
        useToastStore().addToast({
            title: "Server error",
            content: "An error occurred duplicating the event.",
            type: "danger",
        });
    }
};

/**
 * User requests to edit the item
 * @param {Event} item The event item.
 */
const handleEdit = (item: Event) => {
    router.push({
        name: "dashboard-event-edit",
        params: { id: item.id },
        query: {
            return: encodeURIComponent(
                window.location.pathname + window.location.search,
            ),
        },
    });
};

/**
 * Request to delete an event item from the server.
 * @param {Event} item The event object to delete.
 */
const handleDelete = async (item: Event) => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete Event?",
        text: `Are you sure you want to delete the event <strong>${item.title}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete",
        },
    });

    if (result == true) {
        try {
            await api.delete({
                url: "/events/{id}",
                params: {
                    id: item.id,
                },
            });

            const index = items.value.findIndex(
                (lookupItem) => item.id === lookupItem.id,
            );
            if (index !== -1) {
                items.value.splice(index, 1);
            }

            toastStore.addToast({
                title: "Event Deleted",
                content: `The event ${item.title} has been deleted.`,
                type: "success",
            });
        } catch (error) {
            toastStore.addToast({
                title: "Error Deleting Event",
                content:
                    error.data?.message ||
                    "An unexpected server error occurred",
                type: "danger",
            });
        }
    }
};

/**
 * Parse Event location for humans.
 * @param {Event} item The event object to delete.
 * @returns {string} human readable location.
 */
const parseEventLocation = (item: Event) => {
    if (item.location == "online") {
        return "Online";
    }

    return item.address;
};

handleLoad();
</script>

<style lang="scss">
.sm-table-events {
    tbody tr td:last-child {
        white-space: nowrap;
    }
}
</style>
