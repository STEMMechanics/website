/**
 * Tests if an object or string is empty.
 *
 * @param {object|string} objOrString The object or string.
 * @returns {boolean} If the object or string is empty.
 */
export const isEmpty = (objOrString: unknown): boolean => {
    if (objOrString == null) {
        return true;
    } else if (typeof objOrString === "string") {
        return objOrString.length == 0;
    } else if (
        typeof objOrString == "object" &&
        Object.keys(objOrString).length === 0
    ) {
        return true;
    }

    return false;
};

/**
 * Returns the file extension
 *
 * @param {string} fileName The filename with extension.
 * @returns {string} The file extension.
 */
export const getFileExtension = (fileName: string): string => {
    if (fileName.includes(".")) {
        return fileName.split(".").pop();
    }

    return "";
};

/**
 * Returns a url to a file type icon based on file name.
 *
 * @param {string} fileName The filename with extension.
 * @returns {string} The url to the file type icon.
 */
export const getFileIconImagePath = (fileName: string): string => {
    const ext = getFileExtension(fileName);
    if (ext.length > 0) {
        return `/img/fileicons/${ext}.png`;
    }

    return "/img/fileicons/unknown.png";
};

/**
 * Returns a url to a file preview icon based on file url.
 *
 * @param {string} url The url of the file.
 * @returns {string} The url to the file preview icon.
 */
export const getFilePreview = (url: string): string => {
    const ext = getFileExtension(fileName);
    if (ext.length > 0) {
        if (/(gif|jpe?g|png)/i.test(ext)) {
            return `${url}?size=thumb`;
        }

        return `/img/fileicons/${ext}.png`;
    }

    return "/img/fileicons/unknown.png";
};

/**
 * Clamps a number between 2 numbers.
 *
 * @param {number} n The number to clamp.
 * @param {number} min The minimum allowable number.
 * @param {number} max The maximum allowable number.
 * @returns {number} The clamped number.
 */
export const clamp = (n: number, min: number, max: number): number => {
    if (n < min) return min;
    if (n > max) return max;
    return n;
};
