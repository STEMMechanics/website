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
                    <template v-else>{{ item[header["value"]] }}</template>
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

    td,
    th {
        padding: 24px 16px;
        text-align: left;
        border-bottom: 1px solid #ccc;
    }

    th {
        font-size: 90%;
    }

    td {
        font-size: 85%;
        background-color: #fff;
    }

    tbody {
        tr {
            &:hover {
                td {
                    background-color: rgba(0, 0, 255, 0.1);
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

    .table td {
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        padding-left: 50%;
        white-space: normal;
        text-align: left;

        &:before {
            position: absolute;
            top: 6px;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: 600;
            content: attr(data-title);
        }
    }
}
</style>
