<template>
    <table class="table">
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
.table {
    border-spacing: 0;
    margin-bottom: 32px;
    border-radius: 50px;
    width: 100%;

    thead tr {
        font-size: 90%;
        white-space: nowrap;

        &:first-child th {
            border-top: 1px solid var(--table-color-border);

            &:first-child {
                border-top-left-radius: 8px;
            }

            &:last-child {
                border-top-right-radius: 8px;
            }
        }
    }

    tbody {
        tr {
            td {
                font-size: 85%;
                background-color: var(--table-color);

                &.long {
                    font-size: 75%;
                }
            }

            &:hover {
                td {
                    background-color: var(--table-color-hover);
                    cursor: pointer;
                }
            }

            &:last-child {
                td {
                    &:first-child {
                        border-bottom-left-radius: 8px;
                    }

                    &:last-child {
                        border-bottom-right-radius: 8px;
                    }
                }
            }
        }
    }

    td,
    th {
        padding: 24px 16px;
        text-align: left;
        border-bottom: 1px solid var(--table-color-border);

        &:first-child {
            border-left: 1px solid var(--table-color-border);
        }

        &:last-child {
            border-right: 1px solid var(--table-color-border);
        }
    }
}

@media only screen and (max-width: 800px) {
    .table {
        display: block;

        thead,
        tbody,
        th,
        td,
        tr {
            display: block;
        }
    }

    .table thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    .table tbody tr {
        border-bottom: 1px solid var(--table-color-border);

        td {
            border: none;
            border-bottom: 1px solid var(--table-color-border);
            position: relative;
            padding: 8px 12px 8px 40%;
            white-space: normal;
            text-align: left;

            &:before {
                position: absolute;
                display: flex;
                align-items: center;
                padding-left: 12px;
                top: 0;
                bottom: 0;
                left: 0;
                width: 35%;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                content: attr(data-title);
            }
        }

        &:hover {
            td {
                border-bottom-color: transparent;
            }
        }

        &:nth-child(even) td {
            background-color: var(--table-color-even);
        }
    }
}
</style>
