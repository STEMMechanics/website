type DebounceCallback = () => void;
type DebounceResult = (...args: unknown[]) => void;

/**
 * Call a function after a delay once.
 *
 * @param {Function} fn The function to call.
 * @param {number} delay The delay before calling function.
 * @returns {void}
 */
export const debounce = (
    fn: DebounceCallback,
    delay: number
): DebounceResult => {
    let timeoutID: NodeJS.Timeout | null = null;
    return (...args) => {
        if (timeoutID != null) {
            clearTimeout(timeoutID);
        }

        // eslint-disable-next-line @typescript-eslint/no-this-alias
        const that = this;
        timeoutID = setTimeout(function () {
            fn.apply(that, args);
        }, delay);
    };
};
