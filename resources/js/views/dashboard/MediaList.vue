<template>
    <SMContainer permission="admin/media">
        <h1>Media</h1>

        <SMMessage
            v-if="formMessage.message"
            :type="formMessage.type"
            :message="formMessage.message"
            :icon="formMessage.icon" />

        <SMToolbar>
            <template #left>
                <SMButton
                    :to="{ name: 'media-upload' }"
                    type="primary"
                    label="Upload Media" />
            </template>
            <template #right>
                <input v-model="search" placeholder="Search" />
            </template>
        </SMToolbar>

        <!-- @click-row="handleClickRow" -->
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
            <template #item-size="item">
                {{ bytesReadable(item.size) }}
            </template>
            <template #item-actions="item">
                <div class="action-wrapper">
                    <SMFileLink :href="item.url" target="_blank" @click.stop=""
                        ><font-awesome-icon icon="cloud-download-outline"
                    /></SMFileLink>
                </div>
            </template>
        </EasyDataTable>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from "vue";
import EasyDataTable from "vue3-easy-data-table";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import { useRouter } from "vue-router";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { openDialog } from "vue3-promise-dialog";
import SMToolbar from "../../components/SMToolbar.vue";
import SMButton from "../../components/SMButton.vue";
import { debounce } from "../../helpers/debounce";
import { bytesReadable } from "../../helpers/types";
import SMMessage from "../../components/SMMessage.vue";
import SMFileLink from "../../components/SMFileLink.vue";
import { useUserStore } from "../../store/UserStore";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";

const router = useRouter();
const search = ref("");
const userStore = useUserStore();

const headers = [
    { text: "Name", value: "title", sortable: true },
    { text: "Size", value: "size", sortable: true },
    { text: "Permission", value: "permission", sortable: true },
    { text: "Uploaded By", value: "username", sortable: true },
    { text: "Created", value: "created_at", sortable: true },
    { text: "Updated", value: "updated_at", sortable: true },
    { text: "Actions", value: "actions" },
];

const items = ref([]);
let users = {};
const formLoading = ref(false);
const formMessage = reactive({
    message: "",
    type: "error",
    icon: "",
});
const serverItemsLength = ref(0);
const serverOptions = ref({
    page: 1,
    rowsPerPage: 25,
    sortBy: null,
    sortType: null,
});

const loadFromServer = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "alert-circle-outline";
    formMessage.message = "";

    try {
        let params = {};
        if (serverOptions.value.sortBy) {
            params["sort"] = serverOptions.value.sortBy;
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

        let res = await api.get({
            url: "/media",
            params: params,
        });
        if (!res.data.media) {
            throw new Error("The server is currently not available");
        }

        items.value = res.data.media;

        items.value.forEach(async (row) => {
            if (Object.keys(users).includes(row.user_id) === false) {
                await api.get(`users/${row.user_id}`).then((res) => {
                    users[row.user_id] = res.data.user.username;
                });
            }

            if (Object.keys(users).includes(row.user_id)) {
                row["username"] = users[row.user_id];
            } else {
                row["username"] = "--";
            }

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
        });

        serverItemsLength.value = res.data.total;
    } catch (err) {
        // formMessage.message = parseErrorTyp(err);
    }

    formLoading.value = false;
};

loadFromServer();

watch(
    serverOptions,
    (value) => {
        loadFromServer();
    },
    { deep: true }
);

const debouncedFilter = debounce(loadFromServer, 1000);
watch(search, (value) => {
    debouncedFilter();
});

const handleClickRow = (item) => {
    router.push({ name: "media-edit", params: { id: item.id } });
};

const handleEdit = (item) => {
    router.push({ name: "media-edit", params: { id: item.id } });
};

const handleDelete = async (item) => {
    let result = await openDialog(DialogConfirm, {
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
            await api.delete(`media/${item.id}`);
            loadFromServer();
        } catch (err) {
            alert(
                err.response?.data?.message ||
                    "An unexpected server error occurred"
            );
        }
    }
};

const handleDownload = (item) => {
    window.open(item.url, "_blank");
};
</script>
