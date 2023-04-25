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
    border: 1px solid var(--table-color-border);
    border-collapse: collapse;
    margin-bottom: 32px;
    width: 100%;

    td,
    th {
        padding: 24px 16px;
        text-align: left;
        border-bottom: 1px solid var(--table-color-border);
    }

    th {
        font-size: 90%;
        white-space: nowrap;
    }

    td {
        font-size: 85%;
        background-color: var(--table-color);

        &.long {
            font-size: 75%;
        }
    }

    tbody {
        tr {
            &:hover {
                td {
                    background-color: var(--table-color-hover);
                    cursor: pointer;
                }
            }

            &:last-child {
                td {
                    border-bottom: 0;
                }
            }
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
