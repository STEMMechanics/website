import { mergeAttributes, Node } from "@tiptap/core";

export interface InfoOptions {
    HTMLAttributes: Record<string, any>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        info: {
            /**
             * Toggle a paragraph
             */
            setInfo: () => ReturnType;
            toggleInfo: () => ReturnType;
        };
    }
}

export const Info = Node.create<InfoOptions>({
    name: "info",

    priority: 1000,

    addOptions() {
        return {
            HTMLAttributes: { class: "info" },
        };
    },

    group: "block",

    content: "inline*",

    parseHTML() {
        return [{ tag: "p", class: "info" }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            "p",
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
            0,
        ];
    },

    addCommands() {
        return {
            setInfo:
                () =>
                ({ commands }) => {
                    return commands.setNode(this.name);
                },
            toggleInfo:
                () =>
                ({ commands }) => {
                    return commands.toggleNode(this.name, "paragraph");
                },
        };
    },
});
