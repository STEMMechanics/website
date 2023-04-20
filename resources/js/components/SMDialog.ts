/* eslint-disable @typescript-eslint/no-explicit-any */
import {
    AllowedComponentProps,
    Component,
    defineComponent,
    shallowReactive,
    VNodeProps,
} from "vue";

export interface DialogInstance {
    comp?: any;
    dialog: Component;
    wrapper: string;
    props: unknown;
    resolve: (data: unknown) => void;
}
const dialogRefs = shallowReactive<DialogInstance[]>([]);

export default defineComponent({
    name: "SMDialogList",
    template: `
    <div class="dialog-list">
        <div v-for="(dialogRef, index) in dialogRefList" :key="index" class="dialog-outer">
                <component
                    :is="dialogRef.dialog"
                    v-if="dialogRef && dialogRef.wrapper === name"
                    v-bind="dialogRef.props"
                    :ref="(ref) => (dialogRef.comp = ref)"></component>
        </div>
    </div>
    `,
    data() {
        const dialogRefList = dialogRefs;

        return {
            name: "default",
            transitionAttrs: {},
            dialogRefList,
        };
    },
});

/**
 * Closes last opened dialog, resolving the promise with the return value of the dialog, or with the given
 * data if any.
 *
 * @param {unknown} data The dialog return value.
 */
export function closeDialog(data?: unknown) {
    if (dialogRefs.length <= 1) {
        document.getElementsByTagName("html")[0].style.overflow = "";
        document.getElementsByTagName("body")[0].style.overflow = "";
    }

    const lastDialog = dialogRefs.pop();
    if (data === undefined) {
        data = lastDialog.comp.returnValue();
    }
    lastDialog.resolve(data);
}

/**
 * Extracts the type of props from a component definition.
 */
type PropsType<C extends Component> = C extends new (...args: any) => any
    ? Omit<
          InstanceType<C>["$props"],
          keyof VNodeProps | keyof AllowedComponentProps
      >
    : never;

/**
 * Extracts the return type of the dialog from the setup function.
 */
type BindingReturnType<C extends Component> = C extends new (
    ...args: any
) => any
    ? InstanceType<C> extends { returnValue: () => infer Y }
        ? Y
        : never
    : never;

/**
 * Extracts the return type of the dialog either from the setup method or from the methods.
 */
type ReturnType<C extends Component> = BindingReturnType<C>;

/**
 * Opens a dialog.
 *
 * @param {Component} dialog The dialog you want to open.
 * @param {PropsType} props The props to be passed to the dialog.
 * @param {string} wrapper The dialog wrapper you want the dialog to open into.
 * @returns {Promise} A promise that resolves when the dialog is closed
 */
export function openDialog<C extends Component>(
    dialog: C,
    props?: PropsType<C>,
    wrapper: string = "default"
): Promise<ReturnType<C>> {
    if (dialogRefs.length === 0) {
        document.getElementsByTagName("html")[0].style.overflow = "hidden";
        document.getElementsByTagName("body")[0].style.overflow = "hidden";
    }

    return new Promise((resolve) => {
        dialogRefs.push({
            dialog,
            props,
            wrapper,
            resolve,
        });

        window.setTimeout(() => {
            const autofocusElement = document.querySelector("[autofocus]");
            if (autofocusElement) {
                autofocusElement.focus();
            }
        }, 10);
    });
}
