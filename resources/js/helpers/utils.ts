import { useUserStore } from "../store/UserStore";
import { extractFileNameFromUrl } from "./url";

/**
 * Tests if an object or string is empty.
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
 * @param {string} fileName The filename with extension.
 * @returns {string} The url to the file type icon.
 */
export const getFileIconImagePath = (fileName: string): string => {
    const ext = getFileExtension(fileName);
    if (ext.length > 0) {
        return `/assets/fileicons/${ext}.webp`;
    }

    return "/assets/fileicons/unknown.webp";
};

/**
 * Returns a url to a file preview icon based on file url.
 * @param {string} url The url of the file.
 * @returns {string} The url to the file preview icon.
 */
export const getFilePreview = (url: string): string => {
    const ext = getFileExtension(extractFileNameFromUrl(url));
    if (ext.length > 0) {
        if (/(gif|jpe?g|png)/i.test(ext)) {
            return `${url}?size=thumb`;
        }

        return `/assets/fileicons/${ext}.webp`;
    }

    return "/assets/fileicons/unknown.webp";
};

/**
 * Clamps a number between 2 numbers.
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

type RandomIDVerifyCallback = (id: string) => boolean;

/**
 * Generate a random ID.
 * @param {string} prefix Any prefix to add to the ID.
 * @param {number} length The length of the ID string (default = 6).
 * @param {RandomIDVerifyCallback|null} callback Callback that if returns true generates a ID string.
 * @returns {string} A random string.
 */
export const generateRandomId = (
    prefix: string = "",
    length: number = 6,
    callback: RandomIDVerifyCallback | null = null,
): string => {
    let randomId = "";
    const letters =
        "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";

    do {
        randomId = prefix;
        for (let i = 0; i < length; i++) {
            randomId += letters.charAt(
                Math.floor(Math.random() * letters.length),
            );
        }
    } while (callback != null ? callback(randomId) : false);

    return randomId;
};

/**
 * Generate a random element ID.
 * @param {string} prefix Any prefix to add to the ID.
 * @param {number} length The length of the ID string (default = 6).
 * @returns {string} A random string non-existent in the document.
 */
export const generateRandomElementId = (
    prefix: string = "",
    length: number = 6,
): string => {
    return generateRandomId(prefix, length, (s) => {
        return document.getElementById(s) != null;
    });
};

/**
 * Return if the current user has a permission.
 * @param {string} permission The permission to check.
 * @returns {boolean} If the user has the permission.
 */
export const userHasPermission = (permission: string): boolean => {
    const userStore = useUserStore();
    return userStore.permissions && userStore.permissions.includes(permission);
};

/**
 * Convert File Name to Title
 * @param {string} fileName The filename with extension.
 * @returns {string} The title.
 */
export const convertFileNameToTitle = (fileName: string): string => {
    // Remove file extension
    fileName = fileName.replace(/\.[^/.]+$/, "");

    // Replace underscores with space
    fileName = fileName.replace(/_/g, " ");

    // Replace dashes that are not surrounded by spaces with space
    fileName = fileName.replace(/(?<! )-(?! )/g, " ");

    // Remove double spaces
    fileName = fileName.replace(/\s{2,}/g, " ");

    // Capitalize the first letter and convert to lowercase
    fileName =
        fileName.charAt(0).toUpperCase() + fileName.slice(1).toLowerCase();

    return fileName;
};
