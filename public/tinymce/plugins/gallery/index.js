tinymce.PluginManager.add("gallery", function (editor) {
    // Register a command to open the dialog
    editor.addCommand("mycommand", function () {
        const header = document.createElement("div");
        header.innerHTML = "cats";

        // create the gallery element
        const gallery = document.createElement("div");
        gallery.innerHTML = "dogs";

        editor.windowManager.open({
            title: "Image Gallery",
            size: "large",
            body: {
                type: "tabpanel",
                tabs: [
                    {
                        name: "gallery",
                        title: "Gallery",
                        items: [
                            {
                                type: "htmlpanel",
                                html: header.outerHTML,
                            },
                        ],
                    },
                    {
                        name: "library",
                        title: "Library",
                        items: [
                            {
                                type: "bar",
                                items: [
                                    {
                                        type: "selectbox",
                                        name: "type",
                                        size: 1,
                                        items: [
                                            {
                                                value: "all",
                                                text: "All media types",
                                            },
                                            { value: "jpg", text: "JPEG" },
                                            { value: "png", text: "PNG" },
                                        ],
                                    },
                                    {
                                        type: "selectbox",
                                        name: "date",
                                        size: 1,
                                        items: [
                                            {
                                                value: "all",
                                                text: "Uploaded anytime",
                                            },
                                            { value: "7", text: "Last 7 days" },
                                            {
                                                value: "14",
                                                text: "Last 14 days",
                                            },
                                            {
                                                value: "28",
                                                text: "Last 28 days",
                                            },
                                        ],
                                    },
                                    {
                                        type: "input",
                                        name: "search",
                                        placeholder: "search",
                                    },
                                ],
                            },
                        ],
                    },
                    {
                        name: "upload",
                        title: "Upload",
                        items: [
                            {
                                type: "htmlpanel",
                                html: header.outerHTML,
                            },
                        ],
                    },
                ],
            },
            buttons: [
                {
                    type: "custom",
                    text: "Upload",
                    name: "upload",
                    align: "start",
                },
                {
                    type: "cancel",
                    text: "Cancel",
                    align: "end",
                },
                {
                    type: "submit",
                    text: "Save",
                    name: "ok",
                    primary: true,
                    align: "end",
                },
            ],
            onAction: function (_dialogApi, details) {
                if (details.name === "upload") {
                    // input.click();
                }
            },
            onSubmit: function (e) {
                editor.insertContent(e.data.mytextbox);
            },
        });
    });

    // Register a toggle button that triggers the command and displays the icon
    editor.ui.registry.addToggleButton("gallery", {
        icon: "gallery",
        tooltip: "Image gallery",
        onAction: function () {
            editor.execCommand("mycommand");
        },
        onSetup: function (api) {
            var nodeChangeHandler = function () {
                var node = editor.selection.getNode();
                api.setActive(node && editor.dom.hasClass(node, "sm-gallery"));
            };

            editor.on("NodeChange", nodeChangeHandler);

            return function () {
                editor.off("NodeChange", nodeChangeHandler);
            };
        },
    });
});
