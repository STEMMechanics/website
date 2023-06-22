<template>
    <SMPageStatus v-if="!userHasPermission('admin/analytics')" :status="403" />
    <template v-else>
        <SMMastHead
            title="Analytics"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <div class="max-w-7xl mx-auto mt-8 px-4">
            <div class="flex items-center flex-justify-between mb-8">
                <SMInput
                    v-model="itemSearch"
                    label="Search"
                    class="max-w-xl ml-4"
                    @keyup.enter="handleSearch">
                    <template #append>
                        <button
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
                    {{ "No sessions where found" }}
                </p>
            </div>
            <template v-else>
                <SMPagination
                    v-if="items.length < itemsTotal"
                    v-model="itemsPage"
                    :total="itemsTotal"
                    :per-page="itemsPerPage" />
                <SMTable
                    :headers="headers"
                    :items="items"
                    @row-click="handleView">
                </SMTable>
            </template>
        </div>
    </template>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../../helpers/api";
import { SessionCollection, Session } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { updateRouterParams } from "../../helpers/url";
import { useToastStore } from "../../store/ToastStore";
import SMInput from "../../components/SMInput.vue";
import SMLoading from "../../components/SMLoading.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMTable from "../../components/SMTable.vue";
import { userHasPermission } from "../../helpers/utils";
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
    { text: "Session", value: "id", sortable: true },
    { text: "IP", value: "ip", sortable: true },
    { text: "Started", value: "created_at", sortable: true },
    { text: "Ended", value: "ended_at", sortable: true },
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
            sort: "-id",
        };

        if (itemSearch.value.length > 0) {
            params[
                "filter"
            ] = `id:${itemSearch.value},OR,ip:${itemSearch.value},OR,path:${itemSearch.value}`;
        }

        let result = await api.get({
            url: "/analytics",
            params: params,
        });

        const data = result.data as SessionCollection;
        data.sessions.forEach(async (row) => {
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.created_at, {
                    format: "ymd",
                    utc: true,
                }).format("dd MMM yyyy h:mm AA");
            }

            if (row.ended_at !== "undefined") {
                row.ended_at = new SMDate(row.ended_at, {
                    format: "ymd",
                    utc: true,
                }).format("dd MMM yyyy h:mm AA");
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
 * @param {Session} item The event item.
 */
const handleView = (item: Session) => {
    router.push({
        name: "dashboard-analytics-item",
        params: { id: item.id },
        query: {
            return: encodeURIComponent(
                window.location.pathname + window.location.search
            ),
        },
    });
};

if (userHasPermission("admin/analytics")) {
    handleLoad();
}
</script>
