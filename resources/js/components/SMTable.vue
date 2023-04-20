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
                        <slot
                            :name="`item-${header['value']}`"
                            v-bind="item as any">
                        </slot>
                    </template>
                    <template v-else>
                        {{
                            header["value"]
                                .split(".")
                                .reduce((item, key) => item[key], item)
                        }}
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
</script>

<style lang="scss">
.table {
    border: 1px solid #ccc;
    border-collapse: collapse;
    margin-bottom: 32px;
    width: 100%;

    td,
    th {
        padding: 24px 16px;
        text-align: left;
        border-bottom: 1px solid #ccc;
    }

    th {
        font-size: 90%;
        white-space: nowrap;
    }

    td {
        font-size: 85%;
        background-color: #fff;
    }

    tbody {
        tr {
            &:hover {
                td {
                    background-color: var(--primary-color-hover);
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
        border-bottom: 1px solid #ccc;

        td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding: 8px 12px 8px 50%;
            white-space: normal;
            text-align: left;

            &:before {
                position: absolute;
                padding: 8px 12px;
                top: 0;
                left: 0;
                width: 45%;
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
            background-color: #f8f8f8;
        }
    }
}
</style>
