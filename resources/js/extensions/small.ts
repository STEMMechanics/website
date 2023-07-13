import { mergeAttributes, Node } from "@tiptap/core";

export interface SmallOptions {
    HTMLAttributes: Record<string, any>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        small: {
            /**
             * Set a small mark
             */
            setSmall: () => ReturnType;
            /**
             * Toggle a small mark
             */
            toggleSmall: () => ReturnType;
        };
    }
}

export const Small = Node.create<SmallOptions>({
    name: "small",
    group: "block",
    content: "inline*",

    addOptions() {
        return {
            HTMLAttributes: { class: "small" },
        };
    },

    parseHTML() {
        return [{ tag: "p.small", priority: 100 }];
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
            setSmall:
                () =>
                ({ commands }) => {
                    return commands.setNode(this.name);
                },
            toggleSmall:
                () =>
                ({ commands }) => {
                    return commands.toggleNode(this.name, "paragraph");
                },
        };
    },
});
