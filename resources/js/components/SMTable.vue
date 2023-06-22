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

        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tbody tr {
            border-bottom: 1px solid var(--table-color-border);

            &:first-child {
                td:first-child {
                    border-top: 1px solid var(--table-color-border);
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
                        border-bottom: 1px solid var(--table-color-border);
                        border-radius: 0 0 8px 8px;
                    }
                }
            }

            td {
                border-bottom: 0;
                border-left: 1px solid var(--table-color-border);
                border-right: 1px solid var(--table-color-border);
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

            &:nth-child(even) td {
                background-color: var(--table-color-even);
            }
        }
    }
}
</style>
