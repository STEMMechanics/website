import { Node } from "@tiptap/core";

export interface SmallOptions {
    HTMLAttributes: Record<string, unknown>;
}

declare module "@tiptap/core" {
    interface Commands<ReturnType> {
        small: {
            /**
             * Toggle a paragraph
             */
            setSmall: () => ReturnType;
            toggleSmall: () => ReturnType;
        };
    }
}

export const Small = Node.create<SmallOptions>({
    name: "small",
    group: "block",
    content: "inline*",
    defining: true,
    priority: 100,

    parseHTML() {
        return [{ tag: "p", class: "small", priority: 51 }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            "p",
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
            0,
        ];
    },

    addOptions() {
        return {
            HTMLAttributes: { class: "small" },
        };
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
/**
 *
 * @param HTMLAttributes
 * @param HTMLAttributes1
 */
function mergeAttributes(
    HTMLAttributes: Record<string, unknown>,
    HTMLAttributes1: Record<string, any>,
): any | string {
    throw new Error("Function not implemented.");
}
