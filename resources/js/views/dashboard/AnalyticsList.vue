<template>
    <SMPage permission="admin/analytics">
        <SMMastHead
            title="Analytics"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <SMContainer class="flex-grow-1">
            <SMToolbar>
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
                <SMNoItems v-if="items.length == 0" text="No Sessions Found" />
                <SMTable
                    v-else
                    :headers="headers"
                    :items="items"
                    @row-click="handleView">
                </SMTable>
            </template>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import { api } from "../../helpers/api";
import {
    EventCollection,
    Event,
    SessionCollection,
    Session,
} from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { updateRouterParams } from "../../helpers/url";
import { useToastStore } from "../../store/ToastStore";
import { toTitleCase } from "../../helpers/string";
import SMButton from "../../components/SMButton.vue";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMInput from "../../components/SMInput.vue";
import SMLoading from "../../components/SMLoading.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMNoItems from "../../components/SMNoItems.vue";
import SMPagination from "../../components/SMPagination.vue";
import SMTable from "../../components/SMTable.vue";
import SMToolbar from "../../components/SMToolbar.vue";

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
        console.log(error);
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

handleLoad();
</script>

<style lang="scss">
.page-dashboard-event-list {
    .toolbar-search {
        max-width: 350px;
    }

    .table tr {
        td:first-of-type,
        td:nth-of-type(2) {
            word-break: break-all;
        }

        td:not(:first-of-type) {
            white-space: nowrap;
        }
    }
}

@media only screen and (max-width: 768px) {
    .page-dashboard-event-list {
        .toolbar-search {
            max-width: none;
        }
    }
}
</style>
