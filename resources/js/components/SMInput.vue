<template>
    <div :class="['form-group', { 'has-error': error }]">
        <label v-if="label" :class="{ required: required, inline: inline }">{{
            label
        }}</label>
        <input
            v-if="
                type == 'text' ||
                type == 'password' ||
                type == 'email' ||
                type == 'url'
            "
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            @input="input"
            @blur="handleBlur"
            @keydown="handleBlur" />
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
                <font-awesome-icon v-else icon="fa-regular fa-image" />
            </div>
            <div v-if="type == 'media'" class="form-group-error">
                {{ error }}
            </div>
            <a class="button" @click.prevent="handleMediaSelect">Select file</a>
        </div>
        <div v-if="type != 'media'" class="form-group-error">{{ error }}</div>
        <div v-if="slots.default" class="form-group-info">
            <slot></slot>
        </div>
        <div v-if="help" class="form-group-help">
            <font-awesome-icon v-if="helpIcon" :icon="helpIcon" />
            {{ help }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, useSlots, ref, watch } from "vue";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import { openDialog } from "vue3-promise-dialog";
import axios from "axios";

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

const handleChange = (event) => {
    emits("update:modelValue", event.target.files[0]);
    emits("blur", event);
};

const input = (event) => {
    emits("update:modelValue", event.target.value);
};

const handleBlur = (event) => {
    if (event.keyCode == undefined || event.keyCode == 9) {
        emits("blur", event);
    }
};

const handleMediaSelect = async (event) => {
    let result = await openDialog(SMDialogMedia);

    console.log(result);
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
            let result = await axios.get(`media/${props.modelValue}`);
            mediaUrl.value = result.data.medium.url;
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
.input-media-group {
    display: flex;
    margin: 0 auto;
    max-width: 26rem;
    flex-direction: column;
    align-items: center;

    .input-media-display {
        display: flex;
        margin-bottom: 1rem;
        border: 1px solid $border-color;
        background-color: #fff;

        img {
            max-width: 100%;
            max-height: 100%;
        }

        svg {
            padding: 4rem;
        }
    }

    .button {
        max-width: 13rem;
    }
}

.input-media-group + .form-group-error {
    text-align: center;
}

@media screen and (max-width: 768px) {
    .input-media-group {
        max-width: 13rem;
    }
}
</style>
