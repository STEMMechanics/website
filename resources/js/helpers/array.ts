/**
 * Test if array has a match using basic search (* means anything)
 *
 * @param {Array<string>} arr The array to search.
 * @param {string} str The string to find.
 * @returns {boolean} if the array has the string.
 */
export const arrayHasBasicMatch = (
    arr: Array<string>,
    str: string
): boolean => {
    let matches = false;

    arr.every((elem) => {
        elem = elem.replace(/[|\\{}()[\]^$+?.]/g, "\\$&");
        const regex = new RegExp("^" + elem.replace("*", ".*?") + "$", "i");
        if (str.match(regex)) {
            matches = true;
        }
        return !matches;
    });

    return matches;
};
