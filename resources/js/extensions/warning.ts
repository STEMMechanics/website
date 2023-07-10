import { mergeAttributes, Node } from "@tiptap/core";

export interface WarningOptions {
    HTMLAttributes: Record<string, any>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        warning: {
            /**
             * Toggle a paragraph
             */
            setWarning: () => ReturnType;
            toggleWarning: () => ReturnType;
        };
    }
}

export const Warning = Node.create<WarningOptions>({
    name: "warning",

    priority: 1000,

    addOptions() {
        return {
            HTMLAttributes: { class: "warning" },
        };
    },

    group: "block",

    content: "inline*",

    parseHTML() {
        return [{ tag: "p", class: "warning" }];
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
            setWarning:
                () =>
                ({ commands }) => {
                    return commands.setNode(this.name);
                },
            toggleWarning:
                () =>
                ({ commands }) => {
                    return commands.toggleNode(this.name, "paragraph");
                },
        };
    },
});
