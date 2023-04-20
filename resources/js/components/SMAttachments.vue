<template>
    <h3 v-if="props.attachments && props.attachments.length > 0">Files</h3>
    <table class="attachment-list">
        <tbody>
            <tr
                v-for="file of props.attachments"
                :key="file.id"
                class="attachment-row">
                <td class="attachment-file-icon">
                    <img
                        :src="getFileIconImagePath(file.name || file.title)"
                        height="40"
                        width="40" />
                </td>
                <td class="attachment-file-name">
                    <a :href="file.url">{{ file.title || file.name }}</a>
                </td>
                <td class="attachment-download">
                    <a :href="file.url + '?download=1'"
                        ><svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg">
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
                <td class="attachment-file-size">
                    ({{ bytesReadable(file.size) }})
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup lang="ts">
import { bytesReadable } from "../helpers/types";
import { getFileIconImagePath } from "../helpers/utils";

const props = defineProps({
    attachments: {
        type: Object,
        required: true,
    },
});
</script>

<style lang="scss">
.attachment-list {
    border: 1px solid $secondary-color;
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    // max-width: 580px;
    margin-top: 12px;
    background-color: var(--base-color-light);

    .attachment-row {
        td {
            padding: 8px 0;
        }

        &:last-child td {
            border-bottom: 0;
        }

        .attachment-file-icon {
            width: 56px;
            padding-left: 8px;

            img {
                display: block;
            }
        }

        .attachment-file-name {
            font-size: 80%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;

            a {
                text-decoration: none;

                &:hover {
                    text-decoration: underline;
                }
            }
        }

        .attachment-download {
            width: 28px;
            text-align: center;

            a {
                display: block;
                color: $secondary-color-dark;
                transition: color 0.2s ease-in-out;

                &:hover {
                    color: $primary-color-dark;
                }

                svg {
                    margin-top: 4px;
                    width: 24px;
                    height: 24px;
                }
            }
        }

        .attachment-file-size {
            width: 80px;
            font-size: 75%;
            color: $secondary-color-dark;
            white-space: nowrap;
            text-align: right;
            padding-right: 8px;
        }
    }
}

@media only screen and (max-width: 640px) {
    .attachment-list {
        .attachment-file-icon img {
            margin: 0 4px;
        }

        .attachment-download a,
        .attachment-file-size {
            padding-left: 0.25rem;
        }
    }
}

@media only screen and (max-width: 440px) {
    .attachment-list {
        .attachment-file-icon {
            display: none;
        }
    }
}
</style>
