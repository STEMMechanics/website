import { mergeAttributes, Node } from "@tiptap/core";

export interface SuccessOptions {
    HTMLAttributes: Record<string, any>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        success: {
            /**
             * Toggle a paragraph
             */
            setSuccess: () => ReturnType;
            toggleSuccess: () => ReturnType;
        };
    }
}

export const Success = Node.create<SuccessOptions>({
    name: "success",

    priority: 1000,

    addOptions() {
        return {
            HTMLAttributes: { class: "success" },
        };
    },

    group: "block",

    content: "inline*",

    parseHTML() {
        return [{ tag: "p", class: "success" }];
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
            setSuccess:
                () =>
                ({ commands }) => {
                    return commands.setNode(this.name);
                },
            toggleSuccess:
                () =>
                ({ commands }) => {
                    return commands.toggleNode(this.name, "paragraph");
                },
        };
    },
});
