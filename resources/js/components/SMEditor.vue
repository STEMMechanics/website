<template>
    <div class="editor">
        <MdEditor
            :preview="false"
            language="en-US"
            v-model="markdown"
            @change="handleChange" />
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { api } from "../helpers/api";
import { MediaCollection, MediaResponse } from "../helpers/api.types";
import { routes } from "../router";
import { urlMatches } from "../helpers/url";
import { mediaGetVariantUrl } from "../helpers/media";

interface PageList {
    title: string;
    value: string;
}

import { MdEditor } from "md-editor-v3";
import "md-editor-v3/lib/style.css";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const emits = defineEmits(["input", "update:modelValue", "blur", "focus"]);

const markdown = ref(props.modelValue);
let timeout = null;

const handleChange = (newValue) => {
    if (timeout != null) {
        clearTimeout(timeout);
    }
    timeout = setTimeout(() => {
        timeout = null;
        emits("update:modelValue", newValue);
    }, 50);
};

const tinyeditor = ref(null);

// const editorContent = ref(props.modelValue);

/* Updating value */
// const handleInitialContentChange = (newContent) => {
//     newContent = newContent === undefined ? "" : newContent;
//     editorContent.value = newContent;
// };

// const initialContent = computed(() => {
//     return props.modelValue;
// });

// watch(initialContent, handleInitialContentChange);

// const handleBlur = (event) => {
//     emits("blur", event);
// };

// const handleFocus = (event) => {
//     emits("focus", event);
// };

// const handleChange = (event, editor) => {
//     emits("update:modelValue", editor.getContent());
// };

// const fetchLinkList = () => {
//     const buildPageList = (
//         pageList,
//         routeEntries,
//         prefix_url = "",
//         prefix_title = ""
//     ) => {
//         routeEntries.forEach((entry) => {
//             if (
//                 "path" in entry &&
//                 entry.path.includes(":") == false &&
//                 "meta" in entry &&
//                 "title" in entry.meta &&
//                 ("hideInEditor" in entry.meta == false ||
//                     entry.meta.hideInEditor == false) &&
//                 ("middleware" in entry.meta == false ||
//                     ("showInEditor" in entry.meta == true &&
//                         entry.meta.showInEditor == true))
//             ) {
//                 const sep = entry.path.substring(0, 1) == "/" ? "" : "/";
//                 pageList[prefix_url + sep + entry.path] =
//                     prefix_title.length > 0
//                         ? `${prefix_title} ${sep} ${entry.meta.title}`
//                         : entry.meta.title.toLowerCase() == "home"
//                         ? entry.meta.title
//                         : `Home / ${entry.meta.title}`;
//             }

//             if ("children" in entry) {
//                 buildPageList(
//                     pageList,
//                     entry.children,
//                     prefix_url + entry.path,
//                     prefix_title + (entry.meta?.title || "")
//                 );
//             }
//         });
//     };

//     let pageRoutes: { [key: string]: string } = {};
//     buildPageList(pageRoutes, routes);

//     const pageList: PageList[] = [];
//     for (const [key, value] of Object.entries(pageRoutes)) {
//         pageList.push({ title: value, value: key });
//     }

//     pageList.sort((a, b) => {
//         const titleA = a.title.toLowerCase();
//         const titleB = b.title.toLowerCase();

//         if (titleA < titleB) {
//             return -1;
//         }

//         if (titleA > titleB) {
//             return 1;
//         }

//         return 0;
//     });

//     return pageList;
// };
</script>

<style lang="scss">
.md-editor {
    border-color: rgba(156, 163, 175);
    border-radius: 0.5rem;

    .md-editor-toolbar-wrapper {
        height: 2.5rem;
        border-bottom-color: rgba(156, 163, 175);

        .md-editor-toolbar-item {
            height: 1.75rem;
        }

        .md-editor-icon {
            height: 1.75rem;
            width: 1.75rem;
        }
    }

    .cm-editor {
        font-size: 1rem;
    }

    .md-editor-footer {
        height: 2.5rem;
        font-size: 0.9rem;
        border-top-color: rgba(156, 163, 175);

        .md-editor-checkbox {
            width: 1rem;
            height: 1rem;
        }
    }
}
</style>
