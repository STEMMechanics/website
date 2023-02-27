import { Ref } from "vue";

/**
 * Return the browser transiton end name.
 *
 * @returns {string} The browser transition end name.
 */
const transitionEndEventName = (): string => {
    const el = document.createElement("div"),
        transitions: Record<string, string> = {
            transition: "transitionend",
            OTransition: "otransitionend",
            MozTransition: "transitionend",
            WebkitTransition: "webkitTransitionEnd",
        };

    for (const i in transitions) {
        if (
            Object.prototype.hasOwnProperty.call(transitions, i) &&
            el.style[i] !== undefined
        ) {
            return transitions[i];
        }
    }

    return "";
};

/**
 * Wait for the element to render as Promise
 *
 * @param elem The
 * @returns
 */
const waitForElementRender = (elem: Ref): Promise<HTMLElement> => {
    return new Promise((resolve) => {
        if (document.contains(elem.value)) {
            return resolve(elem.value as HTMLElement);
        }

        /* eslint-disable @typescript-eslint/no-explicit-any */
        const MutationObserver =
            window.MutationObserver ||
            (window as any).WebKitMutationObserver ||
            (window as any).MozMutationObserver;
        /* eslint-enable @typescript-eslint/no-explicit-any */
        const observer = new MutationObserver(() => {
            if (document.contains(elem.value)) {
                resolve(elem.value);
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    });
};

/**
 * Run the enter transition on a element.
 *
 * @param {Ref} elem The element to run the enter transition.
 * @param {string} transition The transition name.
 * @returns {void}
 */
export const transitionEnter = (elem: Ref, transition: string): void => {
    waitForElementRender(elem).then((e: HTMLElement) => {
        window.setTimeout(() => {
            e.classList.replace(
                transition + "-enter-from",
                transition + "-enter-active"
            );
            const transitionName = transitionEndEventName();
            e.addEventListener(
                transitionName,
                () => {
                    e.classList.replace(
                        transition + "-enter-active",
                        transition + "-enter-to"
                    );
                },
                false
            );
        }, 1);
    });
};

/**
 * Run the exit transition on a element then call a callback.
 *
 * @param {Ref} elem The element to run the enter transition.
 * @param {string} transition The transition name.
 * @param {TransitionLeaveCallback|null} callback The callback to run after the transition finishes.
 * @returns {void}
 */
type TransitionLeaveCallback = () => void;

export const transitionLeave = (
    elem: Ref,
    transition: string,
    callback: TransitionLeaveCallback | null = null
): void => {
    elem.value.classList.remove(transition + "-enter-to");
    elem.value.classList.add(transition + "-leave-from");
    window.setTimeout(() => {
        elem.value.classList.replace(
            transition + "-leave-from",
            transition + "-leave-active"
        );
        const transitionName = transitionEndEventName();
        elem.value.addEventListener(
            transitionName,
            () => {
                elem.value.classList.replace(
                    transition + "-leave-active",
                    transition + "-leave-to"
                );
                if (callback) {
                    callback();
                }
            },
            false
        );
    }, 1);
};
