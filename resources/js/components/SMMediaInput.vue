<template>
    <div
        :class="[
            'sm-input-group',
            { 'sm-input-active': inputActive, 'sm-has-error': error },
        ]">
        <label v-if="label" :class="{ required: required, inline: inline }">{{
            label
        }}</label>
        <ion-icon class="sm-error-icon" name="alert-circle-outline"></ion-icon>
        <input
            v-if="
                type == 'text' ||
                type == 'email' ||
                type == 'password' ||
                type == 'email' ||
                type == 'url'
            "
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            @input="input"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown" />
        <textarea
            v-if="type == 'textarea'"
            rows="5"
            :value="modelValue"
            :placeholder="placeholder"
            @input="input"
            @blur="handleBlur"
            @keydown="handleBlur"></textarea>
        <div v-if="type == 'file'" class="input-file-group">
            <input
                id="file"
                type="file"
                class="file"
                :accept="props.accept"
                @change="handleChange" />
            <label class="button" for="file">Select file</label>
            <div class="file-name">
                {{ modelValue?.name ? modelValue.name : modelValue }}
            </div>
        </div>
        <a v-if="type == 'link'" :href="href" target="_blank">{{
            props.modelValue
        }}</a>
        <span v-if="type == 'static'">{{ props.modelValue }}</span>
        <div v-if="type == 'media'" class="input-media-group">
            <div class="input-media-display">
                <img v-if="mediaUrl.length > 0" :src="mediaUrl" />
                <ion-icon v-else name="image-outline" />
            </div>
            <div v-if="type == 'media'" class="form-group-error">
                {{ error }}
            </div>
            <a class="button" @click.prevent="handleMediaSelect">Select file</a>
        </div>
        <div v-if="slots.default || error" class="sm-input-help">
            <span v-if="type != 'media'" class="sm-input-error">{{
                error
            }}</span>
            <span v-if="slots.default" class="sm-input-info">
                <slot></slot>
            </span>
        </div>
        <div v-if="help" class="form-group-help">
            <ion-icon v-if="helpIcon" name="information-circle-outline" />
            {{ help }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, useSlots, ref, watch } from "vue";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import { openDialog } from "vue3-promise-dialog";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    label: {
        type: String,
        default: "",
        required: false,
    },
    placeholder: {
        type: String,
        default: "",
        required: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: "text",
    },
    error: {
        type: String,
        default: "",
    },
    help: {
        type: String,
        default: "",
    },
    helpIcon: {
        type: String,
        default: "",
    },
    accept: {
        type: String,
        default: "",
    },
    href: {
        type: String,
        default: "",
    },
});

const emits = defineEmits(["update:modelValue", "blur"]);
const slots = useSlots();
const mediaUrl = ref("");
let inputActive = ref(false);

const handleChange = (event) => {
    emits("update:modelValue", event.target.files[0]);
    emits("blur", event);
};

const input = (event) => {
    emits("update:modelValue", event.target.value);
};

const handleBlur = (event) => {
    if (props.modelValue.length == 0) {
        inputActive.value = false;
    }

    if (event.keyCode == undefined || event.keyCode == 9) {
        emits("blur", event);
    }
};

const handleFocus = (event) => {
    inputActive.value = true;
    if (event.keyCode == undefined || event.keyCode == 9) {
        emits("blur", event);
    }
};

const handleKeydown = (event) => {};

const handleMediaSelect = async (event) => {
    let result = await openDialog(SMDialogMedia);
    if (result) {
        mediaUrl.value = result.url;
        emits("update:modelValue", result.id);
    }
};

const inline = computed(() => {
    return ["static", "link"].includes(props.type);
});

const handleLoad = async () => {
    if (props.type == "media" && props.modelValue.length > 0) {
        try {
            let result = await api.get(`/media/${props.modelValue}`);
            mediaUrl.value = result.json.medium.url;
        } catch (error) {
            /* empty */
        }
    }
};

watch(
    () => props.modelValue,
    () => {
        handleLoad();
    }
);
</script>

<style lang="scss">
.sm-input-group {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-bottom: map-get($spacer, 4);

    &.sm-input-active {
        label {
            transform: translate(8px, -3px) scale(0.7);
            color: $secondary-color-dark;
        }

        input {
            padding: calc(#{map-get($spacer, 2)} * 1.5) map-get($spacer, 3)
                calc(#{map-get($spacer, 2)} / 2) map-get($spacer, 3);
        }
    }

    &.sm-has-error {
        input,
        select,
        textarea {
            border: 2px solid $danger-color;
        }

        .sm-error-icon {
            display: block;
        }
    }

    label {
        position: absolute;
        display: block;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        line-height: 1.5;
        transform-origin: top left;
        transform: translate(0, 1px) scale(1);
        transition: all 0.1s ease-in-out;
        color: $font-color;
    }

    .sm-error-icon {
        position: absolute;
        display: none;
        right: 0;
        top: 2px;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        color: $danger-color;
        font-size: 120%;
    }

    input,
    select,
    textarea {
        box-sizing: border-box;
        display: block;
        width: 100%;
        border: 1px solid $border-color;
        border-radius: 12px;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        color: $font-color;
        margin-bottom: map-get($spacer, 1);

        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    textarea {
        resize: none;
    }

    .sm-input-help {
        font-size: 75%;
        margin: 0 map-get($spacer, 1);

        .sm-input-error {
            color: $danger-color;
            padding-right: map-get($spacer, 1);
        }
    }
}

// .form-group {
//     margin-bottom: map-get($spacer, 3);
//     padding: 0 4px;
//     flex: 1;

//     input,
//     textarea {
//         margin-bottom: map-get($spacer, 1);
//     }

//     label {
//         position: absolute;
//     }

//     .form-group-info {
//         font-size: 85%;
//         margin-bottom: map-get($spacer, 1);
//     }

//     .form-group-error {
//         // display: none;
//         font-size: 85%;
//         margin-bottom: map-get($spacer, 1);
//         color: $danger-color;
//     }

//     .form-group-help {
//         font-size: 85%;
//         margin-bottom: map-get($spacer, 1);
//         color: $secondary-color;

//         svg {
//             vertical-align: middle !important;
//         }
//     }

//     &.has-error {
//         input,
//         textarea,
//         .input-file-group,
//         .input-media-group .input-media-display {
//             border: 2px solid $danger-color;
//         }

//         .form-group-error {
//             display: block;
//         }
//     }
// }

// .input-media-group {
//     display: flex;
//     margin: 0 auto;
//     max-width: 26rem;
//     flex-direction: column;
//     align-items: center;

//     .input-media-display {
//         display: flex;
//         margin-bottom: 1rem;
//         border: 1px solid $border-color;
//         background-color: #fff;

//         img {
//             max-width: 100%;
//             max-height: 100%;
//         }

//         svg {
//             padding: 4rem;
//         }
//     }

//     .button {
//         max-width: 13rem;
//     }
// }

// .input-media-group + .form-group-error {
//     text-align: center;
// }

// @media screen and (max-width: 768px) {
//     .input-media-group {
//         max-width: 13rem;
//     }
// }
</style>
