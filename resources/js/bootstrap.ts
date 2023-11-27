import axios, { AxiosStatic } from "axios";
import stemmech, { StemmechStatic } from "./stemmech";
import { Editor } from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";

// Attach axios to the window object
declare global {
    interface Window {
        axios: AxiosStatic;
        stemmech: StemmechStatic;
        SVGInject: any;
        setupEditor: any;
    }
}

window.axios = axios;
window.stemmech = stemmech;

// Set a default header for Axios requests
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Setup window ready
window.stemmech.ready(() => {
    setTimeout(function () {
        window.stemmech.cleanupBackLinks();
        window.stemmech.inputErrorListener();
        window.stemmech.formSubmitListener();
        window.stemmech.formChangeListener();
    }, 1);

    window.SVGInject(document.querySelectorAll("img.injectable"));
});

window.setupEditor = function (content) {
    let editor;

    return {
        content: content,

        init(element) {
            editor = new Editor({
                element: element,
                extensions: [StarterKit],
                content: this.content,
                onUpdate: ({ editor }) => {
                    this.content = editor.getHTML();
                },
            });

            this.$watch("content", (content) => {
                // If the new content matches TipTap's then we just skip.
                if (content === editor.getHTML()) return;

                /*
    Otherwise, it means that a force external to TipTap
    is modifying the data on this Alpine component,
    which could be Livewire itself.
    In this case, we just need to update TipTap's
    content and we're good to do.
    For more information on the `setContent()` method, see:
      https://www.tiptap.dev/api/commands/set-content
  */
                editor.commands.setContent(content, false);
            });
        },
    };
};
