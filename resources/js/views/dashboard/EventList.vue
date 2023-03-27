<template>
    <SMPage permission="admin/events">
        <template #container>
            <SMHeading heading="Events" />
            <SMMessage
                v-if="formMessage.message"
                :icon="formMessage.icon"
                :type="formMessage.type"
                :message="formMessage.message" />
            <SMToolbar>
                <template #left>
                    <SMButton
                        type="primary"
                        label="Create Event"
                        :small="true"
                        @click="handleCreate" />
                </template>
                <template #right>
                    <SMInput
                        v-model="search"
                        label="Search"
                        :small="true"
                        style="max-width: 250px" />
                </template>
            </SMToolbar>

            <EasyDataTable
                v-model:server-options="serverOptions"
                :server-items-length="serverItemsLength"
                :loading="formLoading"
                :headers="headers"
                :items="items"
                :search-value="search">
                <template #loading>
                    <SMLoadingIcon />
                </template>
                <template #item-title="item">
                    <router-link
                        :to="{
                            name: 'dashboard-event-edit',
                            params: { id: item.id },
                        }"
                        >{{ item.title }}</router-link
                    >
                </template>
                <template #item-actions="item">
                    <div class="action-wrapper">
                        <SMButton
                            label="Edit"
                            :dropdown="{
                                duplicate: 'Duplicate',
                                delete: 'Delete',
                            }"
                            @click="handleClick(item, $event)"></SMButton>
                    </div>
                </template>
            </EasyDataTable>
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import EasyDataTable from "vue3-easy-data-table";
import { openDialog } from "../../components/SMDialog";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMButton from "../../components/SMButton.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import SMInput from "../../components/SMInput.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import { debounce } from "../../helpers/debounce";
import { EventCollection, EventResponse } from "../../helpers/api.types";
import { useToastStore } from "../../store/ToastStore";

const router = useRouter();
const search = ref("");

const headers = [
    { text: "Title", value: "title", sortable: true },
    { text: "Starts", value: "start_at_formatted", sortable: true },
    { text: "Created", value: "created_at_formatted", sortable: true },
    { text: "Updated", value: "updated_at_formatted", sortable: true },
    { text: "Actions", value: "actions" },
];

const items = ref([]);
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const formLoading = ref(false);
const serverItemsLength = ref(0);
const serverOptions = ref({
    page: 1,
    rowsPerPage: 25,
    sortBy: "start_at",
    sortType: "desc",
});

const handleClick = (item, extra: string): void => {
    if (extra.length == 0) {
        handleEdit(item);
    } else if (extra.toLowerCase() == "duplicate") {
        handleDuplicate(item);
    } else if (extra.toLowerCase() == "delete") {
        handleDelete(item);
    }
};

const loadFromServer = async () => {
    formMessage.icon = "";
    formMessage.type = "error";
    formMessage.message = "";
    formLoading.value = true;

    try {
        let params = {};
        if (serverOptions.value.sortBy) {
            params["sort"] = serverOptions.value.sortBy.replace(
                "_formatted",
                ""
            );
            if (
                serverOptions.value.sortType &&
                serverOptions.value.sortType === "desc"
            ) {
                params["sort"] = "-" + params["sort"];
            }
        }

        params["page"] = serverOptions.value.page;
        params["limit"] = serverOptions.value.rowsPerPage;

        if (search.value.length > 0) {
            params["title"] = search.value;
        }

        let result = await api.get({
            url: "/events",
            params: params,
        });

        const data = result.data as EventCollection;

        if (!data.events) {
            throw new Error("The server is currently not available");
        }

        items.value = data.events;

        items.value.forEach((row) => {
            if (row.start_at !== "undefined") {
                row.start_at_formatted = new SMDate(row.start_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
            if (row.created_at !== "undefined") {
                row.created_at_formatted = new SMDate(row.created_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
            if (row.updated_at !== "undefined") {
                row.updated_at_formatted = new SMDate(row.updated_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
        });

        serverItemsLength.value = data.total;
    } catch (err) {
        // restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
};

loadFromServer();

watch(
    serverOptions,
    () => {
        loadFromServer();
    },
    { deep: true }
);

const debouncedFilter = debounce(loadFromServer, 1000);
watch(search, () => {
    debouncedFilter();
});

const handleCreate = () => {
    router.push({ name: "dashboard-event-create" });
};

const handleEdit = (item) => {
    router.push({ name: "dashboard-event-edit", params: { id: item.id } });
};

const handleDuplicate = async (item) => {
    const duplicateItem = { ...item };

    try {
        let tries = 1;
        let number = 2;

        let originalTitle = duplicateItem.title;

        const titleMatch = originalTitle.match(/[- ](\d+)$/);
        if (titleMatch !== null) {
            number = parseInt(titleMatch[1], 10);

            originalTitle = originalTitle.replace(
                new RegExp(`[- ]${number}$`),
                ""
            );
        }

        delete duplicateItem.key;
        delete duplicateItem.id;
        delete duplicateItem.created_at;
        delete duplicateItem.updated_at;

        while (tries < 25) {
            const title = `${originalTitle} ${number}`;
            try {
                await api.get({
                    url: `/events/?title==${title}`,
                });
            } catch (err) {
                if (err.status === 404) {
                    duplicateItem.title = `${originalTitle} ${number}`;
                    break;
                } else {
                    useToastStore().addToast({
                        title: "Server error",
                        content: "The event could not be duplicated.",
                        type: "danger",
                    });
                    return;
                }
            }

            ++tries;
            ++number;
        }

        const result = await api.post({
            url: "/events",
            body: duplicateItem,
        });

        const data = result.data as EventResponse;

        loadFromServer();

        useToastStore().addToast({
            title: "Event duplicated",
            content: "The event was duplicated successfully.",
            type: "success",
        });

        router.push({
            name: "dashboard-event-edit",
            params: { id: data.event.id },
        });
    } catch (err) {
        useToastStore().addToast({
            title: "Server error",
            content: "The event could not be duplicated.",
            type: "danger",
        });
    }
};

const handleDelete = async (item) => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete User?",
        text: `Are you sure you want to delete the event <strong>${item.title}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete Post",
        },
    });

    if (result == true) {
        try {
            await api.delete(`events${item.id}`);
            loadFromServer();

            formMessage.message = "Post deleted successfully";
            formMessage.type = "success";
        } catch (err) {
            formMessage.message = err.response?.data?.message;
        }
    }
};
</script>

<style lang="scss"></style>
