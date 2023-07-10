import { mergeAttributes, Node } from "@tiptap/core";

export interface DangerOptions {
    HTMLAttributes: Record<string, any>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        danger: {
            /**
             * Toggle a paragraph
             */
            setDanger: () => ReturnType;
            toggleDanger: () => ReturnType;
        };
    }
}

export const Danger = Node.create<DangerOptions>({
    name: "danger",

    priority: 1000,

    addOptions() {
        return {
            HTMLAttributes: { class: "danger" },
        };
    },

    group: "block",

    content: "inline*",

    parseHTML() {
        return [{ tag: "p", class: "danger" }];
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
            setDanger:
                () =>
                ({ commands }) => {
                    return commands.setNode(this.name);
                },
            toggleDanger:
                () =>
                ({ commands }) => {
                    return commands.toggleNode(this.name, "paragraph");
                },
        };
    },
});
