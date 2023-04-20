<template>
    <SMControl class="control-type-checkbox">
        <div :class="['control-item', { disabled: disabled }]">
            <label class="control-label" v-bind="{ for: id }"
                ><input
                    type="checkbox"
                    class="checkbox-control"
                    :checked="props.modelValue"
                    @blur="handleBlur"
                    @input="handleInput" />
                <span class="checkbox-control-box">
                    <span class="checkbox-control-tick"></span>
                </span>
                {{ label }}</label
            >
        </div>
        {{ id }}
        <template v-if="slots.help" #help><slot name="help"></slot></template>
    </SMControl>
</template>

<script setup lang="ts">
import { inject, watch, ref, useSlots } from "vue";
import { isEmpty } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";
import SMControl from "./SMControl.vue";

const emits = defineEmits(["update:modelValue", "blur"]);
const props = defineProps({
    form: {
        type: Object,
        default: undefined,
        required: false,
    },
    control: {
        type: [String, Object],
        default: "",
    },
    label: {
        type: String,
        default: undefined,
        required: false,
    },
    modelValue: {
        type: Boolean,
        default: false,
        required: true,
    },
    id: {
        type: String,
        default: undefined,
        required: false,
    },
    disabled: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();

const form = inject("form", props.form);
const control =
    typeof props.control === "object"
        ? props.control
        : !isEmpty(form) &&
          typeof props.control === "string" &&
          props.control !== "" &&
          Object.prototype.hasOwnProperty.call(form.controls, props.control)
        ? form.controls[props.control]
        : null;

const label = ref(
    props.label != undefined
        ? props.label
        : typeof props.control == "string"
        ? toTitleCase(props.control)
        : ""
);
const value = ref(
    props.modelValue != undefined
        ? props.modelValue
        : control != null
        ? control.value
        : ""
);
const id = ref(
    props.id != undefined
        ? props.id
        : typeof props.control == "string" && props.control.length > 0
        ? props.control
        : null
);
const disabled = ref(props.disabled);

if (typeof control === "object" && control !== null) {
    watch(
        () => control.value,
        (newValue) => {
            value.value = newValue;
        },
        { deep: true }
    );
}

if (form) {
    watch(
        () => form.loading(),
        (newValue) => {
            disabled.value = newValue;
        }
    );
}

const handleBlur = async () => {
    emits("blur");
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    value.value = target.checked;
    emits("update:modelValue", target.checked);

    if (control) {
        control.value = target.checked;
    }
};
</script>

<style lang="scss">
.control-group.control-type-checkbox {
    .control-row {
        .control-item {
            .control-label {
                display: flex;
                align-items: center;
                height: 24px;
                padding-left: 32px;

                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;

                .checkbox-control {
                    opacity: 0;
                    width: 0;
                    height: 0;

                    &:checked + .checkbox-control-box {
                        .checkbox-control-tick {
                            display: block;
                        }
                    }
                }

                .checkbox-control-box {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 24px;
                    height: 24px;
                    border: 1px solid var(--base-color-darker);
                    border-radius: 2px;
                    background-color: var(--base-color-light);

                    .checkbox-control-tick {
                        position: absolute;
                        display: none;
                        border-right: 3px solid var(--base-color-text);
                        border-bottom: 3px solid var(--base-color-text);
                        top: 1px;
                        left: 7px;
                        width: 8px;
                        height: 16px;
                        transform: rotate(45deg);
                    }
                }
            }

            &.disabled label {
                cursor: not-allowed;
            }
        }
    }
}
</style>
