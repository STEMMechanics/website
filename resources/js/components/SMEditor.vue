<template>
    <div class="sm-editor">
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

const useDarkMode = false; // window.matchMedia("(prefers-color-scheme: dark)").matches;
const tinyeditor = ref(null);

const init = {
    promotion: false,
    emoticons_database_url: "/tinymce/plugins/emoticons/js/emojis.min.js",
    template_cdate_format: "[Date Created (CDATE): %m/%d/%Y : %H:%M:%S]",
    template_mdate_format: "[Date Modified (MDATE): %m/%d/%Y : %H:%M:%S]",
    relative_urls: false,
    skin_url: useDarkMode
        ? "/tinymce/skins/ui/oxide-dark"
        : "/tinymce/skins/ui/oxide",
    content_css: useDarkMode
        ? "/tinymce/skins/content/default/dark.min.css"
        : "/tinymce/skins/content/default/content.min.css",
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
    ],
    toolbar:
        "h1 h2 h3 blockquote | bold italic underline strikethrough | numlist bullist | image media link anchor codesample | alignleft aligncenter alignright alignjustify | forecolor backcolor removeformat | outdent indent | charmap emoticons | undo redo",
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

const imageBrowser = (callback, value, meta) => {
    var libraryPage = 1;
    var libraryMax = 1;
    var selected = value;

    console.log(selected);

    // Open a dialog to select a file
    const input = document.createElement("input");
    input.setAttribute("type", "file");
    input.setAttribute("accept", "image/*");
    input.onchange = function () {
        if (input.files) {
            let formData = new FormData();
            formData.append("file", input.files[0]);

            api.post({
                url: "/media",
                body: formData,
            })
                .then((result) => {
                    input.value = "";
                    const data = result.data as MediaResponse;

                    if (data.medium) {
                        callback(data.medium.url);
                        dialog.close();
                    } else {
                        alert("The server responded with an unknown error");
                    }
                })
                .catch((error) => {
                    input.value = "";
                    alert(
                        error.data.message ||
                            "An unexpected error occurred uploading the file to the server."
                    );
                });
        }
    };

    const updateLibrary = () => {
        const limit = 24;

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

        api.get({
            url: "/media",
            params: {
                limit: limit,
                page: libraryPage,
                mime: "image/",
            },
        })
            .then((result) => {
                const data = result.data as MediaCollection;
                libraryMax = Math.ceil(data.total / limit);

                document.getElementById(
                    "image-library-pagination-max"
                ).innerHTML = libraryMax.toString();

                const libraryContainer = document.getElementById(
                    "image-library-content"
                );
                if (libraryContainer != null) {
                    // delete existing items
                    const divElements =
                        libraryContainer.querySelectorAll("div");
                    divElements.forEach((div) => {
                        div.remove();
                    });

                    // add new items
                    data.media.forEach((medium) => {
                        const img = document.createElement("div");
                        img.classList.add("image-library-content-image");
                        if (urlMatches(medium.url, selected)) {
                            img.classList.add(
                                "image-library-content-image-selected"
                            );
                        }
                        img.style.backgroundImage = `url('${medium.url}?w=200')`;
                        img.style.cursor = "pointer";
                        img.onclick = function () {
                            // Remove the "image-library-content-image-selected" class from all the image elements
                            const images = libraryContainer.querySelectorAll(
                                ".image-library-content-image"
                            );

                            images.forEach((image) => {
                                image.classList.remove(
                                    "image-library-content-image-selected"
                                );
                            });

                            // Add the "image-library-content-image-selected" class to the clicked image element
                            img.classList.add(
                                "image-library-content-image-selected"
                            );
                            selected = medium.url;
                        };

                        libraryContainer.appendChild(img);
                    });
                }
            })
            .catch(() => {
                /* empty */
            });
    };

    // Add the container and file input to the dialog
    const dialog = tinymce.activeEditor.windowManager.open({
        title: "Image Library",
        size: "large",
        body: {
            type: "tabpanel",
            tabs: [
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
                                        <button id="image-library-search-button"><svg width="24" height="24" focusable="false"><path d="M16 17.3a8 8 0 1 1 1.4-1.4l4.3 4.4a1 1 0 0 1-1.4 1.4l-4.4-4.3Zm-5-.3a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill-rule="nonzero"/></svg></button>
                                    </div>
                                    <div class="image-library-pagination">
                                        <button id="image-library-pagination-prev"><svg width="24" height="24" focusable="false"><path d="M15.5 5.5l-7 7 7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                        <span class="image-library-pagination-status"><span id="image-library-pagination-current">1</span> of <span id="image-library-pagination-max">...</span></span>
                                        <button id="image-library-pagination-next"><svg width="24" height="24" focusable="false"><path d="M8.5 18.5l7-7-7-7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    </div>
                                </div>
                                <div id="image-library-content"><div>loading...</div></div>`,
                        },
                    ],
                },
            ],
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
        onChange: function (dialogApi, details) {
            if (details.name == "dropzone") {
                const files = dialogApi.getData();
                if (files && files.length > 0) {
                    let formData = new FormData();
                    formData.append("file", files[0]);

                    api.post({
                        url: "/media",
                        body: formData,
                    })
                        .then((result) => {
                            input.value = "";
                            const data = result.data as MediaResponse;

                            if (data.medium) {
                                callback(data.medium.url);
                                dialog.close();
                            } else {
                                alert(
                                    "The server responded with an unknown error"
                                );
                            }
                        })
                        .catch((error) => {
                            input.value = "";
                            alert(
                                error.data.message ||
                                    "An unexpected error occurred uploading the file to the server."
                            );
                        });
                }
            }
        },
        onTabChange: function (dialogApi, details) {
            if (details.newTabName == "library") {
                updateLibrary();
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
        align-content: center;

        #image-library-search-input {
            width: auto;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        #image-library-search-button {
            border-width: 1px 1px 1px 0;
            border-style: solid;
            border-color: #eee;
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
            padding: 0 8px;
            background-color: #eee;

            &:hover {
                background-color: #ddd;
            }
        }
    }

    .image-library-pagination {
        display: flex;
        flex: 1;
        align-items: center;
        justify-content: flex-end;

        .image-library-pagination-status {
            margin: 0 12px;
        }

        button {
            display: flex;
            cursor: pointer;
            background-color: #eee;
            border-radius: 6px;
            padding: 2px;

            &:hover {
                background-color: #ddd;
            }
        }
    }
}

#image-library-content {
    display: flex;
    flex-wrap: wrap;
    margin-top: 12px;
    border: 1px solid #eee;
    justify-content: center;
    gap: 1rem;
    overflow-y: auto;
    padding: 0.5rem;
    max-height: 460px;

    .image-library-content-image {
        width: 18vw;
        height: 14vh;
        min-height: 113px;
        min-width: 200px;
        background-repeat: no-repeat;
        background-size: cover;
        background-position: center;
        border: 3px solid #fff;
        padding: 2px;
        background-clip: content-box;

        &:hover,
        &.image-library-content-image-selected {
            border: 3px solid #0060ce;
            position: relative;
        }

        &.image-library-content-image-selected::before {
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
            color: #fff;
            box-shadow: 0 0 2px 2px #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-color: #0060ce;
        }
    }
}

@media only screen and (max-height: 600px) {
    #tinymce-library {
        max-height: 428px;
    }
}

@media only screen and (max-height: 570px) {
    #tinymce-library {
        height: 60vh;
    }
}
</style>
