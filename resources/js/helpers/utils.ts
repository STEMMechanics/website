import { extractFileNameFromUrl } from "./url";

/**
 * Tests if an object or string is empty.
 *
 * @param {unknown} value The object or string.
 * @returns {boolean} If the object or string is empty.
 */
export const isEmpty = (value: unknown): boolean => {
    if (typeof value === "string") {
        return value.trim().length === 0;
    } else if (
        value instanceof File ||
        value instanceof Blob ||
        value instanceof Map ||
        value instanceof Set
    ) {
        return value.size === 0;
    } else if (value instanceof FormData) {
        return [...value.entries()].length === 0;
    } else if (typeof value === "object") {
        return !value || Object.keys(value).length === 0;
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
    const ext = getFileExtension(extractFileNameFromUrl(url));
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

/**
 * Generate a random element ID.
 *
 * @param {string} prefix Any prefix to add to the ID.
 * @returns {string} A random string non-existent in the document.
 */
export const generateRandomElementId = (prefix: string = ""): string => {
    let randomId = "";

    do {
        randomId = prefix + Math.random().toString(36).substring(2, 9);
    } while (document.getElementById(randomId));

    return randomId;
};
