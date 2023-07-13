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
        return [{ tag: "p.small", priority: 51 }];
    },

    renderHTML() {
        return ["p", { class: "small" }, ["small", 0]];
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
