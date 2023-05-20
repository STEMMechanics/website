<template>
    <div class="editor">
        <Editor
            ref="tinyeditor"
            v-model="editorContent"
            model-events="change blur focus"
            output-format="html"
            :init="init"
            :disabled="props.disabled"
            @blur="handleBlur"
            @focus="handleFocus"
            @change="handleChange" />
    </div>
</template>

<script setup lang="ts">
import Editor from "@tinymce/tinymce-vue";
import "tinymce/tinymce";
import "tinymce/themes/silver";

import "tinymce/icons/default";
import "tinymce/models/dom";

import "tinymce/plugins/advlist";
import "tinymce/plugins/anchor";
import "tinymce/plugins/autolink";
import "tinymce/plugins/autosave";
import "tinymce/plugins/charmap";
import "tinymce/plugins/code";
import "tinymce/plugins/codesample";
import "tinymce/plugins/directionality";
import "tinymce/plugins/emoticons";
import "tinymce/plugins/fullscreen";
import "tinymce/plugins/help";
import "tinymce/plugins/image";
import "tinymce/plugins/importcss";
import "tinymce/plugins/insertdatetime";
import "tinymce/plugins/link";
import "tinymce/plugins/lists";
import "tinymce/plugins/media";
import "tinymce/plugins/nonbreaking";
import "tinymce/plugins/pagebreak";
import "tinymce/plugins/preview";
import "tinymce/plugins/searchreplace";
import "tinymce/plugins/table";
import "tinymce/plugins/template";
import "tinymce/plugins/visualblocks";
import "tinymce/plugins/visualchars";
import "tinymce/plugins/wordcount";

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

const tinyeditor = ref(null);

tinymce.PluginManager.add("gallery", function (editor) {
    // Add styling
    editor.on("PreInit", function () {
        var contentStyle = editor.options.get("content_style") || "";
        contentStyle += `
        .tinymce-gallery {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            width: 100%;
            border: 3px solid #666;
        }

        .tinymce-gallery-item {
            background-size: cover;
            background-position: center;
            position: relative;
            padding-bottom: 56.25%;
        }

        .tinymce-gallery::before {
            position: absolute;
            content: "";
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.5);
            z-index: 100;
        }

        .tinymce-gallery::after {
            position: absolute;
            content: "Image Gallery";
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            background-color: #666;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 200%;
            color: #fff;
            padding: .25rem 1.5rem;
            z-index: 110;
        }`;
        editor.options.set("content_style", contentStyle);
    });

    // Register a command to open the dialog
    editor.addCommand("image-gallery", function () {
        var selected = [];
        var node = editor.selection.getNode();

        if (node) {
            if (!editor.dom.hasClass(node, "tinymce-gallery")) {
                // Check if node is a descendant of a gallery node
                var galleryNode = editor.dom.getParent(
                    node,
                    ".tinymce-gallery"
                );
                if (!galleryNode) {
                    node = null;
                } else {
                    node = galleryNode;
                }
            }
        }

        if (node) {
            // Parse the gallery contents
            const childEls = node.querySelectorAll("div");
            const urls = Array.from(childEls).map((el) => {
                const matches = (el as HTMLElement)
                    .getAttribute("style")
                    .match(/url\(['"]?(.*?)['"]?\)/);
                return matches ? matches[1] : null;
            });
            selected = urls;
        }
        imageBrowser(
            function (url) {
                let galleryContent = "";
                if (url.length > 0) {
                    url.forEach((item) => {
                        galleryContent += `<div class="tinymce-gallery-item" style="background-image:url('${item}');"></div>`;
                    });

                    galleryContent = `<div contentEditable="false" class="tinymce-gallery">${galleryContent}</div>`;
                }

                const selection = editor.selection;
                if (selection) {
                    selection.setContent(galleryContent);
                } else {
                    editor.insertContent(galleryContent);
                }
            },
            selected,
            null,
            true
        );
    });

    // Register a toggle button that triggers the command and displays the icon
    editor.ui.registry.addToggleButton("gallery", {
        icon: "gallery",
        tooltip: "Image gallery",
        onAction: function () {
            editor.execCommand("image-gallery");
        },
        onSetup: function (api) {
            var nodeChangeHandler = function () {
                var node = editor.selection.getNode();

                api.setActive(
                    node &&
                        (editor.dom.hasClass(node, "tinymce-gallery") ||
                            (node.parentNode &&
                                editor.dom.hasClass(
                                    node.parentNode,
                                    "tinymce-gallery"
                                )))
                );
            };

            editor.on("NodeChange", nodeChangeHandler);

            return function () {
                editor.off("NodeChange", nodeChangeHandler);
            };
        },
    });
});

const init = {
    promotion: false,
    emoticons_database_url: "/tinymce/plugins/emoticons/js/emojis.min.js",
    // template_cdate_format: "[Date Created (CDATE): %m/%d/%Y : %H:%M:%S]",
    // template_mdate_format: "[Date Modified (MDATE): %m/%d/%Y : %H:%M:%S]",
    relative_urls: false,
    skin_url: "/tinymce/skins/ui/stemmech",
    content_css: "/tinymce/skins/ui/stemmech/content.min.css",
    height: 600,
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
        "anchor",
        "insertdatetime",
        "media",
        "help",
        "codesample",
        "nonbreaking",
        "importcss",
        "directionality",
        "visualchars",
        "emoticons",
        "autosave",
        "searchreplace",
        "gallery",
    ],
    toolbar:
        "h1 h2 h3 blockquote | bold italic underline strikethrough | numlist bullist | image media gallery link anchor codesample | alignleft aligncenter alignright alignjustify | forecolor backcolor removeformat | outdent indent | charmap emoticons | undo redo",
    branding: false,
    menubar: false,
    toolbar_mode: "sliding",
    autosave_ask_before_unload: true,
    autosave_interval: "30s",
    autosave_prefix: "{path}{query}-{id}-",
    autosave_restore_when_empty: false,
    autosave_retention: "2m",
    image_advtab: true,
    codesample_global_prismjs: true,
    codesample_languages: [
        { text: "Bash", value: "bash" },
        { text: "C", value: "c" },
        { text: "C++", value: "cpp" },
        { text: "C#", value: "csharp" },
        { text: "CSS", value: "css" },
        { text: "HTML/XML", value: "markup" },
        { text: "Java", value: "java" },
        { text: "JavaScript", value: "javascript" },
        { text: "Objective-C", value: "objectivec" },
        { text: "Perl", value: "perl" },
        { text: "PHP", value: "php" },
        { text: "Python", value: "python" },
        { text: "Regex", value: "regex" },
        { text: "Ruby", value: "ruby" },
        { text: "SQL", value: "sql" },
        { text: "Swift", value: "swift" },
        { text: "YAML", value: "yml" },
    ],
    link_title: false,
    link_list: (success) => {
        const links = fetchLinkList();
        success(links);
    },
    file_picker_callback: function (callback, value, meta) {
        imageBrowser(callback, value, meta);
    },
};

const editorContent = ref(props.modelValue);

const emits = defineEmits(["input", "update:modelValue", "blur", "focus"]);

/* Updating value */
const handleInitialContentChange = (newContent) => {
    newContent = newContent === undefined ? "" : newContent;
    editorContent.value = newContent;
};

const initialContent = computed(() => {
    return props.modelValue;
});

watch(initialContent, handleInitialContentChange);

const handleBlur = (event) => {
    emits("blur", event);
};

const handleFocus = (event) => {
    emits("focus", event);
};

const handleChange = (event, editor) => {
    emits("update:modelValue", editor.getContent());
};

const fetchLinkList = () => {
    const buildPageList = (
        pageList,
        routeEntries,
        prefix_url = "",
        prefix_title = ""
    ) => {
        routeEntries.forEach((entry) => {
            if (
                "path" in entry &&
                entry.path.includes(":") == false &&
                "meta" in entry &&
                "title" in entry.meta &&
                ("hideInEditor" in entry.meta == false ||
                    entry.meta.hideInEditor == false) &&
                ("middleware" in entry.meta == false ||
                    ("showInEditor" in entry.meta == true &&
                        entry.meta.showInEditor == true))
            ) {
                const sep = entry.path.substring(0, 1) == "/" ? "" : "/";
                pageList[prefix_url + sep + entry.path] =
                    prefix_title.length > 0
                        ? `${prefix_title} ${sep} ${entry.meta.title}`
                        : entry.meta.title.toLowerCase() == "home"
                        ? entry.meta.title
                        : `Home / ${entry.meta.title}`;
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

    let pageRoutes: { [key: string]: string } = {};
    buildPageList(pageRoutes, routes);

    const pageList: PageList[] = [];
    for (const [key, value] of Object.entries(pageRoutes)) {
        pageList.push({ title: value, value: key });
    }

    pageList.sort((a, b) => {
        const titleA = a.title.toLowerCase();
        const titleB = b.title.toLowerCase();

        if (titleA < titleB) {
            return -1;
        }

        if (titleA > titleB) {
            return 1;
        }

        return 0;
    });

    return pageList;
};

const imageBrowser = (callback, value, meta, gallery = false) => {
    var libraryPage = 1;
    var libraryMax = 1;
    var selected = value;
    var title = "";
    var itemsFound = 0;

    const updateFooter = () => {
        let selectedText = "";

        if (gallery == true) {
            selectedText = ` (${selected.length} selected)`;
        }

        const itemCountElem = document.getElementById(
            "image-library-item-count"
        );
        if (itemCountElem) {
            itemCountElem.innerHTML = `${itemsFound.toString()} image${
                itemsFound == 1 ? "" : "s"
            } found${selectedText}`;
        }
    };

    const updateLibrary = () => {
        const limit = 24;

        const searchFunc = function () {
            libraryPage = 1;

            title = (
                document.getElementById(
                    "image-library-search-input"
                ) as HTMLInputElement
            ).value;

            updateLibrary();
        };

        document.getElementById("image-library-pagination-current").innerHTML =
            libraryPage.toString();

        document.getElementById("image-library-pagination-prev").onclick =
            function () {
                if (libraryPage > 1) {
                    libraryPage--;
                    updateLibrary();
                }
            };

        document.getElementById("image-library-pagination-next").onclick =
            function () {
                if (libraryPage < libraryMax) {
                    libraryPage++;
                    updateLibrary();
                }
            };

        document.getElementById("image-library-search-button").onclick =
            searchFunc;
        document.getElementById("image-library-search-input").onkeydown =
            function (event) {
                if (event.key === "Enter") {
                    searchFunc();
                }
            };

        const libraryContainer = document.getElementById(
            "image-library-content"
        );
        if (libraryContainer != null) {
            // delete existing items
            const divElements = libraryContainer.querySelectorAll("div");
            divElements.forEach((div) => {
                div.remove();
            });

            const loadingElem = document.createElement("div");
            loadingElem.classList.add("image-library-content-loading");
            libraryContainer.appendChild(loadingElem);

            api.get({
                url: "/media",
                params: {
                    limit: limit,
                    page: libraryPage,
                    mime: "image/",
                    title: title,
                },
            })
                .then((result) => {
                    const data = result.data as MediaCollection;

                    libraryMax = Math.ceil(data.total / limit);
                    itemsFound = data.total;

                    const libraryContainer = document.getElementById(
                        "image-library-content"
                    );

                    // add new items
                    data.media.forEach((medium) => {
                        const item = document.createElement("div");
                        item.classList.add("image-library-content-item");
                        if (urlMatches(medium.url, selected) !== false) {
                            item.classList.add(
                                "image-library-content-item-selected"
                            );
                        }

                        item.onclick = function () {
                            const items = libraryContainer.querySelectorAll(
                                ".image-library-content-item"
                            );

                            if (gallery == false) {
                                items.forEach((item) => {
                                    item.classList.remove(
                                        "image-library-content-item-selected"
                                    );
                                });

                                item.classList.add(
                                    "image-library-content-item-selected"
                                );
                                selected = medium.url;
                            } else {
                                const match = urlMatches(medium.url, selected);
                                if (match !== false) {
                                    selected.splice(match, 1);
                                    item.classList.remove(
                                        "image-library-content-item-selected"
                                    );
                                } else {
                                    selected.push(medium.url);
                                    item.classList.add(
                                        "image-library-content-item-selected"
                                    );
                                }

                                updateFooter();
                            }
                        };

                        const image = document.createElement("div");
                        image.classList.add("image-library-content-item-image");
                        image.style.backgroundImage = `url('${mediaGetVariantUrl(
                            medium,
                            "small"
                        )}')`;

                        const title = document.createElement("div");
                        title.classList.add("image-library-content-item-title");
                        title.innerHTML = medium.title;

                        item.appendChild(image);
                        item.appendChild(title);

                        libraryContainer.appendChild(item);
                    });
                })
                .catch(() => {
                    libraryMax = 1;
                    itemsFound = 0;
                })
                .finally(() => {
                    loadingElem.remove();

                    const paginationMax = document.getElementById(
                        "image-library-pagination-max"
                    );
                    if (paginationMax) {
                        paginationMax.innerHTML = libraryMax.toString();
                    }
                    updateFooter();
                });
        }
    };

    const updateGallery = () => {
        const galleryContainer = document.getElementById(
            "image-gallery-content"
        );
        if (galleryContainer != null) {
            // delete existing items
            const divElements = galleryContainer.querySelectorAll("div");
            divElements.forEach((div) => {
                div.remove();
            });

            const loadingElem = document.createElement("div");
            loadingElem.classList.add("image-gallery-content-loading");
            galleryContainer.appendChild(loadingElem);

            selected.forEach((url, index) => {
                const item = document.createElement("div");
                item.classList.add("image-gallery-content-item");

                const image = document.createElement("div");
                image.classList.add("image-gallery-content-item-image");
                image.style.backgroundImage = `url('${url}')`;

                const title = document.createElement("div");
                title.classList.add("image-gallery-content-item-title");
                title.innerHTML = "";

                const removeBtn = document.createElement("div");
                removeBtn.classList.add("image-gallery-content-item-remove");
                removeBtn.onclick = function () {
                    selected.splice(index, 1);
                    updateGallery();
                };

                const leftBtn = document.createElement("div");
                leftBtn.classList.add("image-gallery-content-item-left");
                leftBtn.onclick = function () {
                    if (index > 0) {
                        const temp = selected[index];
                        selected[index] = selected[index - 1];
                        selected[index - 1] = temp;

                        updateGallery();
                    }
                };

                const rightBtn = document.createElement("div");
                rightBtn.classList.add("image-gallery-content-item-right");
                rightBtn.onclick = function () {
                    if (index < selected.length - 1) {
                        const temp = selected[index];
                        selected[index] = selected[index + 1];
                        selected[index + 1] = temp;

                        updateGallery();
                    }
                };

                item.appendChild(image);
                item.appendChild(title);
                item.appendChild(removeBtn);
                item.appendChild(leftBtn);
                item.appendChild(rightBtn);

                galleryContainer.appendChild(item);
            });

            const countElem = document.getElementById(
                "image-gallery-item-count"
            );
            if (countElem) {
                countElem.innerHTML = `${selected.length} item${
                    selected.length == 1 ? "" : "s"
                }`;
            }

            loadingElem.remove();
        }
    };

    const tabs = [
        {
            name: "upload",
            title: "Upload",
            items: [
                {
                    type: "dropzone",
                    name: "dropzone",
                    label: "Upload File",
                    accept: "image/*",
                },
            ],
        },
        {
            name: "library",
            title: "Library",
            items: [
                {
                    type: "htmlpanel",
                    html: `<div class="image-library">
                                <div id="image-library-toolbar">
                                    <div class="image-library-search-group">
                                        <input type="text" id="image-library-search-input" placeholder="search" class="tox-textfield" />
                                        <button id="image-library-search-button"><svg width="20" height="20" focusable="false"><path d="M14 15.7a6 6 0 1 1 1.06-1.06l3.54 3.54a1 1 0 0 1-1.06 1.06l-3.54-3.54Zm-4-.4a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z" fill-rule="nonzero"/></svg></button>
                                    </div>
                                    <div class="image-library-pagination">
                                        <button id="image-library-pagination-prev"><svg width="24" height="24" focusable="false"><path d="M15.5 5.5l-7 7 7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                        <span class="image-library-pagination-status"><span id="image-library-pagination-current">1</span> of <span id="image-library-pagination-max">...</span></span>
                                        <button id="image-library-pagination-next"><svg width="24" height="24" focusable="false"><path d="M8.5 18.5l7-7-7-7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    </div>
                                </div>
                                <div id="image-library-content"></div>
                                <div id="image-library-item-count">...</div>
                            </div>`,
                },
            ],
        },
    ];

    if (gallery == true) {
        tabs.push({
            name: "gallery",
            title: "Gallery",
            items: [
                {
                    type: "htmlpanel",
                    html: `<div class="image-gallery">
                                <div id="image-gallery-content"></div>
                                <div id="image-gallery-item-count">...</div>
                            </div>`,
                },
            ],
        });
    }

    // Add the container and file input to the dialog
    const dialog = tinymce.activeEditor.windowManager.open({
        title: "Image Library",
        size: "large",
        body: {
            type: "tabpanel",
            tabs: tabs,
        },
        initialData: {},
        buttons: [
            {
                type: "submit",
                text: "Insert",
            },
        ],
        onSubmit: function (dialogApi) {
            callback(selected);
            dialog.close();
        },
        onChange: async function (dialogApi, details) {
            if (details.name == "dropzone") {
                const files = dialogApi.getData().dropzone || [];
                if (files && files.length > 0) {
                    const uploadElem = document.createElement("div");
                    uploadElem.classList.add("image-gallery-content-upload");
                    document
                        .getElementsByTagName("body")[0]
                        .appendChild(uploadElem);

                    for (let i = 0; i < files.length; i++) {
                        let formData = new FormData();
                        formData.append("file", files[0]);

                        try {
                            let progressText = [
                                `Uploading File ${i + 1}/${files.length}`,
                                "",
                            ];

                            let result = await api.post({
                                url: "/media",
                                body: formData,
                                headers: {
                                    "Content-Type": "multipart/form-data",
                                },
                                progress: (progressData) => {
                                    progressText[1] = `${Math.floor(
                                        (progressData.loaded /
                                            progressData.total) *
                                            100
                                    )}%`;
                                    uploadElem.innerHTML =
                                        progressText.join("<br />");
                                },
                            });

                            if (result.data) {
                                const data = result.data as MediaResponse;

                                if (
                                    data.medium.status != "" &&
                                    data.medium.status.startsWith("Failed") ==
                                        false
                                ) {
                                    progressText[1] = `${data.medium.status}...`;
                                    uploadElem.innerHTML =
                                        progressText.join("<br />");

                                    let mediaProcessed = false;

                                    while (mediaProcessed == false) {
                                        await new Promise((resolve) =>
                                            setTimeout(resolve, 500)
                                        );

                                        try {
                                            let updateResult = await api.get({
                                                url: "/media/{id}",
                                                params: {
                                                    id: data.medium.id,
                                                },
                                            });

                                            if (updateResult.data) {
                                                const updateData =
                                                    updateResult.data as MediaResponse;
                                                if (
                                                    updateData.medium.status ==
                                                        "OK" &&
                                                    data.medium.status.startsWith(
                                                        "Failed"
                                                    ) == false
                                                ) {
                                                    mediaProcessed = true;

                                                    if (gallery == false) {
                                                        callback(
                                                            mediaGetVariantUrl(
                                                                updateData.medium
                                                            )
                                                        );
                                                        dialog.close();
                                                    } else {
                                                        selected.push(
                                                            mediaGetVariantUrl(
                                                                updateData.medium
                                                            )
                                                        );
                                                        dialogApi.showTab(
                                                            "gallery"
                                                        );
                                                    }
                                                } else {
                                                    progressText[1] = `${updateData.medium.status}...`;
                                                    uploadElem.innerHTML =
                                                        progressText.join(
                                                            "<br />"
                                                        );
                                                }
                                            } else {
                                                throw "error";
                                            }
                                        } catch {
                                            mediaProcessed = true;
                                            alert(
                                                "An server error occurred processing the file"
                                            );
                                        }
                                    }
                                }
                            }
                        } catch (error) {
                            input.value = "";
                            alert(
                                error.data.message ||
                                    "An unexpected error occurred uploading the file to the server."
                            );
                        }
                    }

                    uploadElem.parentNode.removeChild(uploadElem);
                }
            }
        },
        onTabChange: function (dialogApi, details) {
            if (details.newTabName == "library") {
                updateLibrary();
            } else if (details.newTabName == "gallery") {
                updateGallery();
            }
        },
    });
};
</script>

<style lang="scss">
.editor {
    width: 100%;
    margin-bottom: 1rem;
}

#image-library-toolbar {
    display: flex;
    margin-bottom: 4px;

    .image-library-search-group {
        display: flex;
        flex: 1;
        align-content: center;
        justify-content: flex-end;
        margin-right: 12px;

        #image-library-search-input {
            width: auto;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            padding: 4px 8px;
            font-size: 90%;
            min-height: auto;
        }

        #image-library-search-button {
            border-width: 1px 1px 1px 0;
            border-style: solid;
            border-color: var(--base-color-border);
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
            padding: 0 8px;
            background-color: var(--base-color);

            &:hover {
                background-color: var(--base-color-dark);
            }
        }
    }

    .image-library-pagination {
        display: flex;
        align-items: center;
        justify-content: flex-end;

        .image-library-pagination-status {
            margin: 0 12px;
        }

        button {
            display: flex;
            cursor: pointer;
            background-color: var(--base-color);
            border-radius: 6px;
            padding: 2px;

            &:hover {
                background-color: var(--base-color-dark);
            }
        }
    }
}

#image-library-content,
#image-gallery-content {
    display: flex;
    flex-wrap: wrap;
    margin-top: 12px;
    border: 1px solid #eee;
    justify-content: center;
    gap: 1rem;
    overflow-y: auto;
    padding: 0.5rem;
    height: 440px;

    .image-library-content-item,
    .image-gallery-content-item {
        position: relative;
        width: 18vw;
        height: 18vh;
        min-width: 200px;
        min-height: 150px;
        border: 3px solid #fff;
        padding: 2px;
        background-clip: content-box;

        &:hover,
        &.image-library-content-item-selected {
            border: 3px solid #0060ce;
            cursor: pointer;
        }

        &.image-library-content-item-selected::before {
            content: "\2713";
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--base-color-light);
            box-shadow: 0 0 0 2px #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-color: var(--primary-color);
        }

        .image-gallery-content-item-remove {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--base-color-light);
            box-shadow: var(--base-shadow);
            background-repeat: no-repeat;
            background-position: center;
            background-size: 50%;
            background-color: var(--danger-color);
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="fill:white"><path d="M170.5 51.6L151.5 80h145l-19-28.4c-1.5-2.2-4-3.6-6.7-3.6H177.1c-2.7 0-5.2 1.3-6.7 3.6zm147-26.6L354.2 80H368h48 8c13.3 0 24 10.7 24 24s-10.7 24-24 24h-8V432c0 44.2-35.8 80-80 80H112c-44.2 0-80-35.8-80-80V128H24c-13.3 0-24-10.7-24-24S10.7 80 24 80h8H80 93.8l36.7-55.1C140.9 9.4 158.4 0 177.1 0h93.7c18.7 0 36.2 9.4 46.6 24.9zM80 128V432c0 17.7 14.3 32 32 32H336c17.7 0 32-14.3 32-32V128H80zm80 64V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16z" /></svg>');
        }

        .image-gallery-content-item-left {
            position: absolute;
            top: -10px;
            right: 40px;
            width: 20px;
            height: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            box-shadow: var(--base-shadow);
            background-repeat: no-repeat;
            background-position: center;
            background-size: 50%;
            background-color: #0060ce;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" style="fill:white"><path d="M9.4 278.6c-12.5-12.5-12.5-32.8 0-45.3l128-128c9.2-9.2 22.9-11.9 34.9-6.9s19.8 16.6 19.8 29.6l0 256c0 12.9-7.8 24.6-19.8 29.6s-25.7 2.2-34.9-6.9l-128-128z"/></svg>');
        }

        .image-gallery-content-item-right {
            position: absolute;
            top: -10px;
            right: 15px;
            width: 20px;
            height: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            box-shadow: 0 0 0 2px #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 50%;
            background-color: #0060ce;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" style="fill:white"><path d="M246.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-128-128c-9.2-9.2-22.9-11.9-34.9-6.9s-19.8 16.6-19.8 29.6l0 256c0 12.9 7.8 24.6 19.8 29.6s25.7 2.2 34.9-6.9l128-128z"/></svg>');
        }

        &:first-of-type .image-gallery-content-item-left {
            display: none;
        }

        &:last-of-type {
            .image-gallery-content-item-left {
                right: 15px;
            }

            .image-gallery-content-item-right {
                display: none;
            }
        }

        .image-library-content-item-image,
        .image-gallery-content-item-image {
            width: 100%;
            height: 14vh;
            min-height: 113px;
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            background-clip: content-box;
        }

        .image-library-content-item-title,
        .image-gallery-content-item-title {
            margin-top: 8px;
            text-align: center;
            font-size: 90%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
}

#image-library-item-count,
#image-gallery-item-count {
    font-size: 90%;
    margin-top: 8px;
    color: #999;
    text-align: right;
}

.image-library-content-loading,
.image-gallery-content-loading {
    position: relative;

    &::after {
        content: "";
        display: block;
        position: absolute;
        top: 50%;
        left: 50%;
        width: 40px;
        height: 40px;
        margin: -20px 0 0 -20px;
        border-radius: 50%;
        border: 4px solid #ccc;
        border-top-color: #333;
        animation: spin 1s ease-in-out infinite;
    }
}

.image-gallery-content-upload {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background-color: #ffffffe8;
    color: #000000;
    z-index: 10000;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media only screen and (max-width: 767px) {
    #image-library-content {
        height: 408px;
    }

    #image-gallery-content {
        height: 408px;
    }
}

@media only screen and (max-width: 450px) {
    #image-library-toolbar {
        flex-direction: column;

        .image-library-search-group {
            margin-bottom: 8px;

            #image-library-search-input {
                width: 100%;
            }
        }

        .image-library-pagination {
            justify-content: center;
        }
    }

    #image-library-content {
        height: 380px;
    }

    #image-gallery-content {
        height: 400px;
    }
}
</style>
