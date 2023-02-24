/**
 * Tests if an object or string is empty.
 *
 * @param {object|string} objOrString The object or string.
 * @returns {boolean} If the object or string is empty.
 */
export const isEmpty = (objOrString: object | string): boolean => {
    if (objOrString) {
        if (typeof objOrString === "string") {
            return objOrString.length == 0;
        } else if (
            typeof objOrString == "object" &&
            Object.keys(objOrString).length === 0
        ) {
            return true;
        }
    }

    return false;
};

/**
 * Returns a url to a file type icon based on file name.
 *
 * @param {string} fileName The filename with extension.
 * @returns {string} The url to the file type icon.
 */
export const getFileIconImagePath = (fileName: string): string => {
    const ext = fileName.split(".").pop();
    return `/img/fileicons/${ext}.png`;
};

/**
 * Returns a url to a file preview icon based on file url.
 *
 * @param {string} url The url of the file.
 * @returns {string} The url to the file preview icon.
 */
export const getFilePreview = (url: string): string => {
    const ext = url.split(".").pop();
    if (ext) {
        if (/(gif|jpe?g|png)/i.test(ext)) {
            return `${url}?w=200`;
        }

        return `/img/fileicons/${ext}.png`;
    }

    return "/img/fileicons/unknown.png";
};
