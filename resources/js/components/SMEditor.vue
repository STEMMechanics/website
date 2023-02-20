<template>
    <div class="editor">
        <label v-if="label" :class="{ required: required }">{{ label }}</label>
        <editor id="tinymce" v-model="editorContent" :init="init" />
        <input
            :id="computedId"
            type="hidden"
            :name="inputName"
            :value="editorContent" />
    </div>
</template>

<script setup lang="ts">
import "tinymce/tinymce";
import Editor from "@tinymce/tinymce-vue";
import "tinymce/themes/silver";

import "tinymce/icons/default";
import "tinymce/models/dom";

import "tinymce/plugins/image"; // 插入上传图片插件
import "tinymce/plugins/media"; // 插入视频插件
import "tinymce/plugins/table"; // 插入表格插件
import "tinymce/plugins/lists"; // 列表插件
import "tinymce/plugins/advlist";
import "tinymce/plugins/link"; // 列表插件
import "tinymce/plugins/autolink";
import "tinymce/plugins/lists";
import "tinymce/plugins/link";
import "tinymce/plugins/image";
import "tinymce/plugins/charmap";
import "tinymce/plugins/searchreplace";
import "tinymce/plugins/visualblocks";
import "tinymce/plugins/code";
import "tinymce/plugins/fullscreen";
// import "tinymce/plugins/print";
import "tinymce/plugins/preview";
import "tinymce/plugins/anchor";
import "tinymce/plugins/insertdatetime";
import "tinymce/plugins/media";
import "tinymce/plugins/help";
import "tinymce/plugins/table";
import "tinymce/plugins/importcss";
import "tinymce/plugins/directionality";
import "tinymce/plugins/visualchars";
import "tinymce/plugins/template";
import "tinymce/plugins/codesample";
// import "tinymce/plugins/hr";
import "tinymce/plugins/pagebreak";
import "tinymce/plugins/nonbreaking";
// import "tinymce/plugins/toc";
// import "tinymce/plugins/imagetools";
// import "tinymce/plugins/textpattern";
// import "tinymce/plugins/noneditable";
import "tinymce/plugins/emoticons";
import "tinymce/plugins/autosave";

// import Trix from "trix";
import { ref, watch, computed, onUnmounted } from "vue";
import { arrayHasBasicMatch } from "../helpers/array";

import DialogMedia from "./dialogs/SMDialogMedia.vue";
import { openDialog } from "vue3-promise-dialog";
import { routes } from "../router";

const props = defineProps({
    disabledEditor: {
        type: Boolean,
        required: false,
        default: false,
    },
    srcContent: {
        type: String,
        required: false,
        default: "",
    },
    inputId: {
        type: String,
        required: false,
        default: "",
    },
    inputName: {
        type: String,
        required: false,
        default: "content",
    },
    placeholder: {
        type: String,
        required: false,
        default: "",
    },
    label: {
        type: String,
        default: "",
        required: false,
    },
    required: {
        type: Boolean,
        required: false,
    },
    mimeTypes: {
        type: Array,
        default() {
            return [];
        },
        required: false,
    },
    removeButtons: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
});

/*                height: 500,
                menubar: false,
                plugins: [
                    'advlist autolink lists link image charmap print preview anchor',
                    'searchreplace visualblocks code fullscreen',
                    'insertdatetime media table paste code help wordcount',
                ],
                toolbar:
                    'undo redo | formatselect | bold italic backcolor | \
           alignleft aligncenter alignright alignjustify | \
           bullist numlist outdent indent | removeformat | help',
*/

const init = {
    promotion: false,
    // emoticons_database_url: "/tinymce/plugins/emoticons/js/emojis.min.js",
    skin_url: "/tinymce/skins/ui/oxide",
    content_css: "/tinymce/skins/content/default/content.min.css",
    height: 500,
    plugins: [
        "link",
        "autolink",
        "lists",
        "advlist",
        "image",
        "table",
        "charmap",
        "searchreplace",
        "visualblocks",
        "code",
        "fullscreen",
        "preview",
        "anchor",
        "insertdatetime",
        "media",
        "help",
        "codesample",
        "pagebreak",
        "nonbreaking",
        "importcss",
        "directionality",
        "visualchars",
        // "emoticons",
        "autosave",
    ],
    toolbar:
        "undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media template link anchor codesample | ltr rtl",
    branding: false,
    menubar: true,
    // https://www.tiny.cloud/docs/configure/file-image-upload/#images_upload_handler
    images_upload_handler: (blobInfo, success, failure) => {
        console.log(blobInfo);
        console.log(success);
        console.log(failure);

        const img = "data:image/jpeg;base64," + blobInfo.base64();
        console.log(img);
        success(img);
    },
};

const trix = ref(null);
const editorContent = ref(props.srcContent);
const isActive = ref(null);
const isInitalized = ref(false);
const initalizeQueue = ref([]);

const emits = defineEmits([
    "input",
    "update",
    "update:srcContent",
    "trix-file-accept",
    "trix-attachment-add",
    "trix-attachment-remove",
    "trix-selection-change",
    "trix-initialize",
    "trix-before-initialize",
    "trix-focus",
    "trix-blur",
]);

const handleContentChange = (event) => {
    editorContent.value = event.srcElement
        ? event.srcElement.value
        : event.target.value;
    emits("input", editorContent.value);
};

const handleInitialize = () => {
    isInitalized.value = true;

    if (props.removeButtons) {
        props.removeButtons.forEach((b) => {
            trix.value.toolbarElement
                .querySelectorAll(`[data-trix-attribute="${b}"]`)
                .forEach((e) => e.remove());
            trix.value.toolbarElement
                .querySelectorAll(`[data-trix-action="${b}"]`)
                .forEach((e) => e.remove());
        });
    }

    // if(!props.allowMedia) {
    //     trix.value.toolbarElement.querySelectorAll('[data-trix-action="attachFiles"]').forEach(e => e.remove())
    // }

    initalizeQueue.value.forEach((item) => item());

    decorateDisabledEditor(props.disabledEditor);
    emits("trix-initialize");
};

const handleInitialContentChange = (newContent, oldContent) => {
    newContent = newContent === undefined ? "" : newContent;

    // if (trix.value && trix.value.innerHTML !== newContent) {
    editorContent.value = newContent;
    // }

    // if (!isActive.value) {
    //     reloadEditorContent(editorContent.value);
    // }
};

const emitEditorState = (value) => {
    emits("update", editorContent.value);
    emits("update:srcContent", editorContent.value);
};

const emitFileAccept = (event) => {
    if (props.mimeTypes) {
        if (!arrayHasBasicMatch(props.mimeTypes, event.file.type)) {
            window.alert("That file type is not supported");
            event.preventDefault();
            return;
        }
    }

    emits("trix-file-accept", event);
};

const emitAttachmentAdd = (event) => {
    emits("trix-attachment-add", event);
};

const emitAttachmentRemove = (event) => {
    emits("trix-attachment-remove", event);
};

const emitSelectionChange = (event) => {
    emits("trix-selection-change", trix.value.editor, event);
};

const emitBeforeInitialize = async (event) => {
    whenInitalized(() => {
        emits("trix-before-initialize", trix.value.editor, event);
    });
};

const processTrixFocus = (event) => {
    isActive.value = true;
    emits("trix-focus", trix.value.editor, event);
};

const processTrixBlur = (event) => {
    isActive.value = false;
    emits("trix-blur", trix.value.editor, event);
};
const whenInitalized = (func) => {
    if (isInitalized.value) {
        func();
    } else {
        initalizeQueue.value.push(func);
    }
};

const reloadEditorContent = async (newContent) => {
    whenInitalized(() => {
        // trix.value.editor.loadHTML(newContent);
        // trix.value.editor.setSelectedRange(getContentEndPosition());
        // console.log(Trix.config);
        // console.log(trix.value.toolbarElement);
    });
};

const decorateDisabledEditor = async (editorState) => {
    whenInitalized(() => {
        if (editorState) {
            trix.value.toolbarElement.style["pointer-events"] = "none";
            trix.value.contentEditable = false;
            trix.value.style["background"] = "#e9ecef";
        } else {
            trix.value.toolbarElement.style["pointer-events"] = "unset";
            trix.value.style["pointer-events"] = "unset";
            trix.value.style["background"] = "#ffffff";
        }
    });
};

const getContentEndPosition = () => {
    return trix.value.editor.getDocument().toString().length - 1;
};

const randomId = () => {
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
        var r = (Math.random() * 16) | 0;
        var v = c === "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
};

const generatedId = computed(() => {
    return randomId();
});

const computedId = computed(() => {
    return props.inputId || generatedId.value;
});

const initialContent = computed(() => {
    return props.srcContent;
});

const isDisabled = computed(() => {
    return props.disabledEditor;
});

watch(editorContent, emitEditorState);
watch(initialContent, handleInitialContentChange);
watch(isDisabled, decorateDisabledEditor);

/** Extra Toolbar Buttons */

const addToolbarButton = async (name, options, func) => {
    if (props.removeButtons && props.removeButtons.includes(name)) {
        return;
    }

    whenInitalized(() => {
        options.type = options.type || "attribute";
        options.icon = options.icon || "?";
        options.group = options.group || "text";
        options.position = options.position || "beforeend";
        options.id = options.id || randomId();
        options.attribute = options.attribute || name;

        if (
            options.trixAttribute &&
            options.trixAttribute.type &&
            options.trixAttribute.data &&
            Trix.config[options.trixAttribute.type + "Attributes"]
        ) {
            Trix.config[options.trixAttribute.type + "Attributes"][name] =
                options.trixAttribute.data;
        }

        if (options.html) {
            options.html = options.html.replace(/%id%/gi, options.id);
            if (func) {
                options.html = options.html.replace(
                    /%func\((.*?)\)%/gi,
                    `Trix.$extensions.${name}(event, '${name}', '${options.id}', $1)`
                );
            }
        }

        if (func) {
            if (Trix.$extensions === undefined) Trix.$extensions = {};
            Trix.$extensions[name] = func;
        }

        trix.value.toolbarElement
            .querySelector(
                `.trix-button-group.trix-button-group--${options.group}-tools`
            )
            .insertAdjacentHTML(
                options.position,
                `${
                    options.divWrap
                        ? '<div style="position:relative" class="trix-button trix-button--icon">'
                        : ""
                }<button type="button" ${
                    options.divWrap
                        ? ""
                        : 'class="trix-button trix-button--icon"'
                } data-trix-${options.type}="${
                    options.type == "attribute" ? name : name
                }" ${
                    func
                        ? `onClick="Trix.$extensions.${name}(event, '${name}', '${options.id}', 'click')"`
                        : ""
                } ${options.title ? `title="${options.title}"` : ""}>${
                    options.icon
                }</button>${options.html ? `${options.html}` : ""}${
                    options.divWrap ? "</div>" : ""
                }`
            );

        if (options.type == "attribute" && options.dialog) {
            trix.value.toolbarElement
                .querySelector(`.trix-dialogs`)
                .insertAdjacentHTML(
                    "beforeend",
                    `<div class="trix-dialog trix-dialog--${name}" data-trix-dialog="${name}" data-trix-dialog-attribute="${options.attribute}">${options.dialog}</div>`
                );
        }
    });
};

/* Foreground and Background Colors - Based on https://github.com/basecamp/trix/issues/985 */
const foregroundColor = {
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M512 256c0 .9 0 1.8 0 2.7c-.4 36.5-33.6 61.3-70.1 61.3H344c-26.5 0-48 21.5-48 48c0 3.4 .4 6.7 1 9.9c2.1 10.2 6.5 20 10.8 29.9c6.1 13.8 12.1 27.5 12.1 42c0 31.8-21.6 60.7-53.4 62c-3.5 .1-7 .2-10.6 .2C114.6 512 0 397.4 0 256S114.6 0 256 0S512 114.6 512 256zM128 288c0-17.7-14.3-32-32-32s-32 14.3-32 32s14.3 32 32 32s32-14.3 32-32zm0-96c17.7 0 32-14.3 32-32s-14.3-32-32-32s-32 14.3-32 32s14.3 32 32 32zM288 96c0-17.7-14.3-32-32-32s-32 14.3-32 32s14.3 32 32 32s32-14.3 32-32zm96 96c17.7 0 32-14.3 32-32s-14.3-32-32-32s-32 14.3-32 32s14.3 32 32 32z"/></svg>',
    group: "text",
    position: "beforeend",
    title: "Text colour",
    html: '<input type="color" style="position:absolute;top:0;left:0;height:100%;width:100%;opacity:0" id="%ID%-picker" onchange="%func(\'colorChanged\')%" />',
    divWrap: true,
    trixAttribute: {
        type: "text",
        data: {
            styleProperty: "color",
            inheritable: true,
        },
    },
};

const backgroundColor = {
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M41.4 9.4C53.9-3.1 74.1-3.1 86.6 9.4L168 90.7l53.1-53.1c28.1-28.1 73.7-28.1 101.8 0L474.3 189.1c28.1 28.1 28.1 73.7 0 101.8L283.9 481.4c-37.5 37.5-98.3 37.5-135.8 0L30.6 363.9c-37.5-37.5-37.5-98.3 0-135.8L122.7 136 41.4 54.6c-12.5-12.5-12.5-32.8 0-45.3zm176 221.3L168 181.3 75.9 273.4c-4.2 4.2-7 9.3-8.4 14.6H386.7l42.3-42.3c3.1-3.1 3.1-8.2 0-11.3L277.7 82.9c-3.1-3.1-8.2-3.1-11.3 0L213.3 136l49.4 49.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0zM512 512c-35.3 0-64-28.7-64-64c0-25.2 32.6-79.6 51.2-108.7c6-9.4 19.5-9.4 25.5 0C543.4 368.4 576 422.8 576 448c0 35.3-28.7 64-64 64z"/></svg>',
    group: "text",
    position: "beforeend",
    title: "Background colour",
    html: '<input type="color" style="position:absolute;top:0;left:0;height:100%;width:100%;opacity:0" id="%ID%-picker" onchange="%func(\'colorChanged\')%" />',
    divWrap: true,
    trixAttribute: {
        type: "text",
        data: {
            styleProperty: "backgroundColor",
            inheritable: true,
        },
    },
};

const fgBgColorFunc = (event, name, id, data) => {
    var picker = document.getElementById(id + "-picker");

    if (data == "colorChanged") {
        trix.value.editor.activateAttribute(name, picker.value);
    }
};

addToolbarButton("foreground", foregroundColor, fgBgColorFunc);
addToolbarButton("background", backgroundColor, fgBgColorFunc);

/* Text align center button - No function needed for this button */
addToolbarButton("textAlignCenter", {
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M352 64c0-17.7-14.3-32-32-32H128c-17.7 0-32 14.3-32 32s14.3 32 32 32H320c17.7 0 32-14.3 32-32zm96 128c0-17.7-14.3-32-32-32H32c-17.7 0-32 14.3-32 32s14.3 32 32 32H416c17.7 0 32-14.3 32-32zM0 448c0 17.7 14.3 32 32 32H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H32c-17.7 0-32 14.3-32 32zM352 320c0-17.7-14.3-32-32-32H128c-17.7 0-32 14.3-32 32s14.3 32 32 32H320c17.7 0 32-14.3 32-32z"/></svg>',
    group: "block",
    position: "beforeend",
    title: "Align text center",
    trixAttribute: {
        type: "block",
        data: {
            tagName: "centered",
        },
    },
});

/* Remove all formatting button */
addToolbarButton(
    "removeFormatting",
    {
        type: "action",
        icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M290.7 57.4L57.4 290.7c-25 25-25 65.5 0 90.5l80 80c12 12 28.3 18.7 45.3 18.7H288h9.4H512c17.7 0 32-14.3 32-32s-14.3-32-32-32H387.9L518.6 285.3c25-25 25-65.5 0-90.5L381.3 57.4c-25-25-65.5-25-90.5 0zM297.4 416H288l-105.4 0-80-80L227.3 211.3 364.7 348.7 297.4 416z"/></svg>',
        group: "text",
        position: "beforeend",
        title: "Remove formatting",
    },
    (event, name, id, data) => {
        // let removeAttrs = ['bold', 'italic', 'strike', 'href', 'foreground', 'background', 'bullet']
        // removeAttrs.forEach(attr => trix.value.editor.deactivateAttribute(attr))

        Object.keys(Trix.config.textAttributes)
            .concat(Object.keys(Trix.config.blockAttributes))
            .forEach((attr) => trix.value.editor.deactivateAttribute(attr));
    }
);

/** Media Selector */
addToolbarButton(
    "media",
    {
        type: "action",
        icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M256 0H576c35.3 0 64 28.7 64 64V288c0 35.3-28.7 64-64 64H256c-35.3 0-64-28.7-64-64V64c0-35.3 28.7-64 64-64zM476 106.7C471.5 100 464 96 456 96s-15.5 4-20 10.7l-56 84L362.7 169c-4.6-5.7-11.5-9-18.7-9s-14.2 3.3-18.7 9l-64 80c-5.8 7.2-6.9 17.1-2.9 25.4s12.4 13.6 21.6 13.6h80 48H552c8.9 0 17-4.9 21.2-12.7s3.7-17.3-1.2-24.6l-96-144zM336 96c0-17.7-14.3-32-32-32s-32 14.3-32 32s14.3 32 32 32s32-14.3 32-32zM64 128h96V384v32c0 17.7 14.3 32 32 32H320c17.7 0 32-14.3 32-32V384H512v64c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V192c0-35.3 28.7-64 64-64zm8 64c-8.8 0-16 7.2-16 16v16c0 8.8 7.2 16 16 16H88c8.8 0 16-7.2 16-16V208c0-8.8-7.2-16-16-16H72zm0 104c-8.8 0-16 7.2-16 16v16c0 8.8 7.2 16 16 16H88c8.8 0 16-7.2 16-16V312c0-8.8-7.2-16-16-16H72zm0 104c-8.8 0-16 7.2-16 16v16c0 8.8 7.2 16 16 16H88c8.8 0 16-7.2 16-16V416c0-8.8-7.2-16-16-16H72zm336 16v16c0 8.8 7.2 16 16 16h16c8.8 0 16-7.2 16-16V416c0-8.8-7.2-16-16-16H424c-8.8 0-16 7.2-16 16z"/></svg>',
        group: "file",
        title: "Insert Media",
    },
    async (event, name, id, data) => {
        let result = await openDialog(DialogMedia);

        if (result.url) {
            trix.value.editor.insertHTML(`<img src="${result.url}" />`);
        }
    }
);

/* Update Link Button */
let hrefDialogObserver = null;
let hrefDialogObserverTarget = null;

onUnmounted(() => {
    if (hrefDialogObserver != null && hrefDialogObserverTarget != null) {
        hrefDialogObserver.unobserve(hrefDialogObserverTarget);

        hrefDialogObserver = null;
        hrefDialogObserverTarget = null;
    }
});

const buildPageList = (
    pageList,
    routeEntries,
    prefix_url = "",
    prefix_title = ""
) => {
    routeEntries.forEach((entry) => {
        if ("path" in entry && "meta" in entry && "title" in entry.meta) {
            const sep = entry.path.substring(0, 1) == "/" ? "" : "/";
            pageList[prefix_url + sep + entry.path] =
                prefix_title + sep + entry.meta.title;
        }

        if ("children" in entry) {
            buildPageList(
                pageList,
                entry.children,
                prefix_url + entry.path,
                prefix_title + (entry.meta?.title || "")
            );
        }
    });
};

/**
 *
 * @param obj
 */
function sortProperties(obj) {
    // convert object into array
    var sortable = [];
    for (var key in obj)
        if (obj.hasOwnProperty(key)) sortable.push([key, obj[key]]); // each item is an array in format [key, value]

    // sort items by value
    sortable.sort(function (a, b) {
        var x = a[1].toLowerCase(),
            y = b[1].toLowerCase();
        return x < y ? -1 : x > y ? 1 : 0;
    });

    obj = {};
    sortable.forEach((item) => {
        obj[item[0]] = item[1];
    });

    return obj; // array in format [ [ key1, val1 ], [ key2, val2 ], ... ]
}

whenInitalized(() => {
    const hrefDialog = trix.value.toolbarElement.querySelector(
        ".trix-dialogs .trix-dialog.trix-dialog--link"
    );

    const hrefInput = hrefDialog.querySelector(".trix-input--dialog");

    const handleHref = (event, name, id, data) => {
        if (id == "select") {
            document.getElementById("href-hidden").value =
                import.meta.env.APP_URL + event.target.value;
            document.getElementById("href-input").value = "";
        } else if (id == "input") {
            document.getElementById("href-hidden").value = event.target.value;
            document.getElementById("href-select").value = "";
        } else {
            /* empty */
        }
    };

    hrefDialogObserverTarget = hrefDialog;
    if (hrefDialogObserver == null && hrefDialogObserverTarget != null) {
        hrefDialogObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.intersectionRatio === 1) {
                    const hidden = document.getElementById("href-hidden");
                    if (hidden != null) {
                        if (hidden.value.startsWith(import.meta.env.APP_URL)) {
                            document.getElementById("href-select").value =
                                hidden.value.substring(
                                    import.meta.env.APP_URL.length
                                );
                            document.getElementById("href-input").value = "";
                        } else {
                            document.getElementById("href-select").value = "";
                            document.getElementById("href-input").value =
                                hidden.value;
                        }
                    }
                }
            });
        }).observe(hrefDialogObserverTarget);
    }

    if (Trix.$extensions === undefined) Trix.$extensions = {};
    Trix.$extensions["href"] = handleHref;

    let pageRoutes = {};
    buildPageList(pageRoutes, routes);

    pageRoutes = sortProperties(pageRoutes);

    hrefInput.removeAttribute("required");
    hrefInput.setAttribute("id", "href-hidden");
    hrefInput.setAttribute(
        "oninput",
        "Trix.$extensions.href(event, 'href', 'href', 'change')"
    );
    hrefInput.insertAdjacentHTML(
        "afterend",
        `<div class="form-group"><label>URL:</label><input id="href-input" type="url" onchange="Trix.$extensions.href(event, 'href', 'input', 'change') data-trix-input"/></div>
        <div class="form-group"><label>Page:</label><select id="href-select" onchange="Trix.$extensions.href(event, 'href', 'select', 'change')">
            <option value=""></option>
            ${Object.keys(pageRoutes)
                .map(function (key) {
                    return (
                        "<option value='" +
                        key +
                        "'>" +
                        pageRoutes[key] +
                        "</option>"
                    );
                })
                .join("")}
        </select></div>`
    );
});
</script>

<style lang="scss">
/* For the added button above */
centered {
    display: block;
    text-align: center;
}

/* Extra Trix Styles to support the above code*/
.trix-button-group {
    .trix-button {
        text-align: -webkit-center;
        text-align: -moz-center;

        svg {
            height: 20px;
            width: 20px;
            opacity: 0.6;
            display: block;
        }

        /* For buttons inside divWrap option */
        button {
            display: block;
            border: none;
            background: transparent;
            height: 100%;
            width: 100%;
        }
    }
}

@media only screen and (max-width: 768px) {
    trix-toolbar .trix-button--icon {
        height: 1.6rem;
    }

    .trix-button-group .trix-button svg {
        height: 14px;
        width: 14px;
    }
}

/* My own theme */
.editor {
    width: 100%;
    margin-bottom: 1rem;

    trix-editor {
        border-radius: 12px;
        border-color: $border-color;
        min-height: 20rem;
        padding: map-get($spacer, 3) map-get($spacer, 2);
        background-color: #fff;

        a span {
            color: $primary-color !important;
        }

        pre {
            word-wrap: break-word;
            white-space: pre-wrap;
        }
    }

    trix-toolbar .trix-dialog__link-fields {
        flex-direction: column;

        #href-hidden {
            // display: none;
        }

        .form-group {
            display: flex;
            align-items: center;
            width: 100%;

            input,
            select,
            label {
                margin: 0;
            }

            label {
                width: 3rem;
            }
        }

        .trix-button-group {
            width: 100%;
            flex-direction: row-reverse;
            border: 0;
            gap: 0.5rem;

            .trix-button {
                border-radius: 12px;
                color: white;
                font-weight: 800;
                border-width: 2px;
                border-style: solid;
                transition: background-color 0.1s, color 0.1s;
                cursor: pointer;
                background-color: $secondary-color;
                border-color: $secondary-color;

                &:hover:not(:disabled) {
                    text-decoration: none;
                    color: $secondary-color;
                    background-color: #fff;
                }

                &:first-of-type {
                    background-color: $primary-color;
                    border-color: $primary-color;

                    &:hover:not(:disabled) {
                        color: $primary-color;
                    }
                }
            }
        }
    }

    .trix-button-row {
        flex-wrap: wrap;
        gap: 0 0.5rem;
        justify-content: flex-start;
    }

    .trix-button-group {
        border-radius: 10px;
        border-color: $border-color;
        background-color: #fff;
        margin-left: 0 !important;

        .trix-button {
            border-bottom: 0;
            border-color: $border-color;

            &::before {
                background-size: 50%;
            }

            &:first-child {
                border-top-left-radius: 10px;
                border-bottom-left-radius: 10px;
            }

            &:last-child {
                border-top-right-radius: 10px;
                border-bottom-right-radius: 10px;
            }

            &:hover:not(:disabled) {
                background-color: rgba(0, 0, 0, 0.1);
            }
        }
    }
}
</style>
