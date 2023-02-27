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
import "tinymce/themes/silver";
import "tinymce/tinymce";

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
    var galleryPage = 1;
    var galleryMax = 1;

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

    // create the header element
    const header = document.createElement("div");
    header.id = "tinymce-gallery-header";

    // create the gallery element
    const gallery = document.createElement("div");
    gallery.id = "tinymce-gallery";

    const updateGallery = () => {
        api.get({
            url: "/media",
            params: {
                limit: 12,
                page: galleryPage,
                mime: "image/",
            },
        })
            .then((result) => {
                const data = result.data as MediaCollection;
                galleryMax = Math.ceil(data.total / 12);

                const infoElement = document.querySelector(
                    "#tinymce-gallery-header .info"
                );
                if (infoElement != null) {
                    infoElement.innerHTML = `${galleryPage} / ${galleryMax}`;
                }

                const galleryContainer =
                    document.getElementById("tinymce-gallery");
                if (galleryContainer != null) {
                    // delete existing items
                    const divElements =
                        galleryContainer.querySelectorAll("div");
                    divElements.forEach((div) => {
                        div.remove();
                    });

                    // add new items
                    data.media.forEach((medium) => {
                        const img = document.createElement("div");
                        img.classList.add("gallery-image");
                        img.style.backgroundImage = `url('${medium.url}?w=200')`;
                        img.style.cursor = "pointer";
                        img.onclick = function () {
                            console.log("click");
                            callback(medium.url);
                            dialog.close();
                        };

                        galleryContainer.appendChild(img);
                    });
                }
            })
            .catch(() => {
                /* empty */
            });
    };

    // Add the container and file input to the dialog
    const dialog = tinymce.activeEditor.windowManager.open({
        title: "Insert image",
        size: "large",
        body: {
            type: "panel",
            items: [
                {
                    type: "htmlpanel",
                    html: header.outerHTML,
                },
                {
                    type: "htmlpanel",
                    html: gallery.outerHTML,
                },
            ],
        },
        buttons: [
            {
                type: "custom",
                text: "Upload",
                name: "upload",
            },
            {
                type: "cancel",
                text: "Cancel",
            },
        ],
        onAction: function (_dialogApi, details) {
            if (details.name === "upload") {
                input.click();
            }
        },
    });

    // create the child elements
    const heading = document.createElement("div");
    heading.className = "heading";
    heading.textContent = "Select an image or upload a new one";

    const pagination = document.createElement("div");
    pagination.className = "pagination";

    const prevButton = document.createElement("button");
    prevButton.className = "prev";
    prevButton.addEventListener("click", () => {
        console.log("prev");
        if (galleryPage > 1) {
            galleryPage--;
            updateGallery();
        }
    });

    const infoDiv = document.createElement("div");
    infoDiv.className = "info";
    infoDiv.textContent = `${galleryPage} / ${galleryMax}`;

    const nextButton = document.createElement("button");
    nextButton.className = "next";
    nextButton.addEventListener("click", () => {
        console.log("next");
        if (galleryPage < galleryMax) {
            galleryPage++;
            updateGallery();
        }
        // handle click on the next button
    });

    // add the child elements to the parent element
    pagination.appendChild(prevButton);
    pagination.appendChild(infoDiv);
    pagination.appendChild(nextButton);

    const renderedHeader = document.getElementById("tinymce-gallery-header");
    if (renderedHeader) {
        renderedHeader.appendChild(heading);
        renderedHeader.appendChild(pagination);
    }

    updateGallery();
};
</script>

<style lang="scss">
.editor {
    width: 100%;
    margin-bottom: 1rem;
}

#tinymce-gallery-header {
    display: flex;

    div.heading {
        flex: 1;
    }

    div.pagination {
        display: flex;
        text-align: right;
        justify-content: center;

        div.info {
            display: inline-block;
        }
    }

    button {
        height: 24px;
        width: 24px;
        position: relative;
        margin: 0 8px;
        padding: 4px 8px;
        border-radius: 4px;
        background-color: #f0f0f0;

        &:before {
            content: "";
            position: absolute;
            height: 8px;
            width: 8px;
        }

        &:hover {
            background-color: #e3e3e3;
        }
    }

    button.prev {
        &:before {
            content: "";
            border-left: 2px solid #222f3e;
            border-bottom: 2px solid #222f3e;
            top: 7px;
            left: 9px;
            transform: rotate(45deg);
        }
    }

    button.next {
        &:before {
            content: "";
            border-right: 2px solid #222f3e;
            border-bottom: 2px solid #222f3e;
            top: 7px;
            right: 9px;
            transform: rotate(-45deg);
        }
    }
}

#tinymce-gallery {
    display: flex;
    flex-wrap: wrap;
    margin-top: 12px;
    border: 1px solid #eee;
    justify-content: center;
    gap: 1rem;
    overflow-y: auto;
    padding: 0.5rem;
    max-height: 468px;

    .gallery-image {
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

        &:hover {
            border: 3px solid #0060ce;
        }
    }
}

@media only screen and (max-height: 600px) {
    #tinymce-gallery {
        max-height: 428px;
    }
}

@media only screen and (max-height: 570px) {
    #tinymce-gallery {
        height: 60vh;
    }
}
</style>
