<template>
    <table class="sm-table">
        <thead>
            <tr>
                <th v-for="header in headers" :key="header['value']">
                    {{ header["text"] }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="(item, index) in items"
                :key="`item-row-${index}`"
                @click="handleRowClick(item)">
                <td
                    v-for="header in headers"
                    :data-title="header['text']"
                    :key="`item-row-${index}-${header['value']}`">
                    <template v-if="slots[`item-${header['value']}`]">
                        <slot :name="`item-${header['value']}`" v-bind="item">
                        </slot>
                    </template>
                    <template v-else>
                        {{ getItemValue(item, header["value"]) }}
                    </template>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

defineProps({
    headers: {
        type: Array,
        default: () => [],
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
        required: true,
    },
});

const emits = defineEmits(["rowClick"]);
const slots = useSlots();

const handleRowClick = (item) => {
    emits("rowClick", item);
};

const getItemValue = (data: unknown, key: string): string => {
    if (typeof data === "object" && data !== null) {
        return key.split(".").reduce((item, key) => item[key], data);
    }

    return "";
};

const hasClassLong = (text: unknown): boolean => {
    if (typeof text == "string") {
        return text.length >= 35;
    }

    return false;
};
</script>

<style lang="scss">
.sm-table {
    border-spacing: 0;
    border-left-width: 1px;
    border-right-width: 1px;
    border-radius: 0.75rem;
    border-color: rgba(209, 213, 219);
    width: 100%;

    thead th {
        background-color: rgba(229, 231, 235, 0.75);
        border-top-width: 1px;
        text-align: left;

        &:first-child {
            border-top-left-radius: 0.75rem;
        }

        &:last-child {
            border-top-right-radius: 0.75rem;
        }
    }

    th,
    td {
        padding: 1rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgba(55, 65, 81);
        border-bottom-width: 1px;
        border-color: rgba(209, 213, 219);
    }

    tbody {
        tr:nth-child(even) td {
            background-color: rgba(229, 231, 235, 0.5);
        }

        tr:last-child td:first-child {
            border-bottom-left-radius: 0.75rem;
        }

        tr:last-child td:last-child {
            border-bottom-right-radius: 0.75rem;
        }
    }
}

@media only screen and (max-width: 800px) {
    .sm-table {
        display: block;

        thead,
        tbody,
        th,
        td,
        tr {
            display: block;
        }

        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tbody tr {
            border-bottom: 1px solid rgba(209, 213, 219);

            &:first-child {
                td:first-child {
                    border-top: 1px solid rgba(209, 213, 219);
                    border-radius: 8px 8px 0 0;
                }
            }

            &:last-child {
                border-bottom: 0;

                td {
                    &:first-child {
                        border-radius: 0;
                    }

                    &:last-child {
                        border-bottom: 1px solid rgba(209, 213, 219);
                        border-radius: 0 0 8px 8px;
                    }
                }
            }

            td {
                border-bottom: 0;
                position: relative;
                padding: 8px 12px 8px 140px;
                white-space: normal;
                word-wrap: break-word;
                text-align: left;

                &:before {
                    position: absolute;
                    display: flex;
                    align-items: center;
                    padding-left: 12px;
                    top: 0;
                    bottom: 0;
                    left: 0;
                    width: 125px;
                    white-space: nowrap;
                    text-align: left;
                    font-weight: 600;
                    content: attr(data-title);
                }
            }

            &:nth-child(even) td {
                background-color: rgba(250, 250, 250);
            }
        }
    }
}
</style>
