<template>
    <div>
        <SMHeader
            v-if="showEditor || (modelValue && modelValue.length > 0)"
            :no-copy="props.showEditor"
            text="Files" />
        <p v-if="props.showEditor" class="small">
            {{ modelValue.length }} file{{ modelValue.length != 1 ? "s" : "" }}
        </p>
        <table
            v-if="modelValue && modelValue.length > 0"
            class="w-full border-1 rounded-2 bg-white text-sm mt-2">
            <tbody>
                <tr v-for="file of fileList" :key="file.id">
                    <td class="py-2 pl-2 hidden sm:block relative">
                        <img
                            :src="getFileIconImagePath(file.name || file.title)"
                            class="h-10 text-center" />
                        <div
                            v-if="
                                file.security_type !== undefined &&
                                file.security_type != ''
                            "
                            class="absolute right--1 top-0 h-4 w-4">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24">
                                <title>locked</title>
                                <path
                                    d="M18,8C19.097,8 20,8.903 20,10L20,20C20,21.097 19.097,22 18,22L6,22C4.903,22 4,21.097 4,20L4,10C4,8.89 4.9,8 6,8L7,8L7,6C7,3.257 9.257,1 12,1C14.743,1 17,3.257 17,6L17,8L18,8M12,3C10.354,3 9,4.354 9,6L9,8L15,8L15,6C15,4.354 13.646,3 12,3Z" />
                            </svg>
                        </div>
                    </td>
                    <td class="pl-2 py-4 w-full">
                        <a rel="nofollow" :href="file.url" target="_blank">{{
                            file.title || file.name
                        }}</a>
                        <p
                            v-if="
                                file.security_type !== undefined &&
                                file.security_type != ''
                            "
                            class="text-xs color-gray">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                class="sm:hidden h-3.5 w-3.5 mb--0.5">
                                <title>locked</title>
                                <path
                                    d="M18,8C19.097,8 20,8.903 20,10L20,20C20,21.097 19.097,22 18,22L6,22C4.903,22 4,21.097 4,20L4,10C4,8.89 4.9,8 6,8L7,8L7,6C7,3.257 9.257,1 12,1C14.743,1 17,3.257 17,6L17,8L18,8M12,3C10.354,3 9,4.354 9,6L9,8L15,8L15,6C15,4.354 13.646,3 12,3Z"
                                    fill="currentColor" />
                            </svg>
                            This file requires additional permission or a
                            password to view
                        </p>
                    </td>
                    <td class="pr-2">
                        <a
                            rel="nofollow"
                            :href="addQueryParam(file.url, 'download', '1')"
                            ><svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 pt-1 text-gray">
                                <path
                                    d="M12 10V20M12 20L9.5 17.5M12 20L14.5 17.5"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M6.3218 7.05726C7.12925 4.69709 9.36551 3 12 3C14.6345 3 16.8708 4.69709 17.6782 7.05726C19.5643 7.37938 21 9.02203 21 11C21 13.2091 19.2091 15 17 15H16C15.4477 15 15 14.5523 15 14C15 13.4477 15.4477 13 16 13H17C18.1046 13 19 12.1046 19 11C19 9.89543 18.1046 9 17 9C16.9776 9 16.9552 9.00037 16.9329 9.0011C16.4452 9.01702 16.0172 8.67854 15.9202 8.20023C15.5502 6.37422 13.9345 5 12 5C10.0655 5 8.44979 6.37422 8.07977 8.20023C7.98284 8.67854 7.55482 9.01702 7.06706 9.0011C7.04476 9.00037 7.02241 9 7 9C5.89543 9 5 9.89543 5 11C5 12.1046 5.89543 13 7 13H8C8.55228 13 9 13.4477 9 14C9 14.5523 8.55228 15 8 15H7C4.79086 15 3 13.2091 3 11C3 9.02203 4.43567 7.37938 6.3218 7.05726Z"
                                    fill="currentColor" />
                            </svg>
                        </a>
                    </td>
                    <td v-if="props.showEditor" class="pr-2">
                        <div
                            class="cursor-pointer text-gray hover:text-red"
                            @click.prevent="handleClickDelete(file.id)">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 pt-1"
                                viewBox="0 0 24 24">
                                <title>Delete</title>
                                <path
                                    d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z"
                                    fill="currentColor" />
                            </svg>
                        </div>
                    </td>
                    <td
                        class="text-xs text-gray whitespace-nowrap pr-2 py-2 hidden sm:table-cell">
                        ({{ bytesReadable(file.size) }})
                    </td>
                </tr>
            </tbody>
        </table>
        <button
            v-if="props.showEditor"
            type="button"
            class="font-medium mt-4 px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
            @click="handleClickAdd">
            Add File
        </button>
    </div>
</template>

<script setup lang="ts">
import { bytesReadable } from "../helpers/types";
import { getFileIconImagePath } from "../helpers/utils";
import { addQueryParam } from "../helpers/url";
import SMHeader from "../components/SMHeader.vue";
import { openDialog } from "../components/SMDialog";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import { Media } from "../helpers/api.types";
import { onMounted, ref, watch } from "vue";
import { mediaGetWebURL } from "../helpers/media";

const emits = defineEmits(["update:modelValue"]);
const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
        required: true,
    },
    showEditor: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const fileList = ref([]);

/**
 * Handle the user adding a new media item.
 */
const handleClickAdd = async () => {
    if (props.showEditor) {
        let result = await openDialog(SMDialogMedia, {
            initial: fileList.value,
            mime: "",
            accepts: "",
            allowUpload: true,
            multiple: true,
        });

        if (result) {
            const mediaResult = result as Media[];
            let newValue = props.modelValue;
            let mediaIds = new Set(newValue.map((item) => (item as Media).id));

            mediaResult.forEach((item) => {
                if (!mediaIds.has(item.id)) {
                    newValue.push(item);
                    mediaIds.add(item.id);
                }
            });

            emits("update:modelValue", newValue);
        }
    }
};

const handleClickDelete = (id: string) => {
    if (props.showEditor == true) {
        const newList = props.modelValue.filter(
            (item) => (item as Media).id !== id,
        );
        emits("update:modelValue", newList);
    }
};

watch(
    () => props.modelValue,
    (newValue) => {
        updateFileList(newValue as Array<Media>);
    },
);

onMounted(() => {
    if (props.modelValue !== undefined) {
        updateFileList(props.modelValue as Array<Media>);
    }
});

const updateFileList = (newFileList: Array<Media>) => {
    fileList.value = [];

    for (const mediaItem of newFileList) {
        mediaItem.url = mediaGetWebURL(mediaItem);
        if (mediaItem.url != "") {
            fileList.value.push(mediaItem);
        }
    }
};
</script>
