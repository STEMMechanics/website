/**
 * Test if target is a boolean
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a boolean type
 */
export function isBool(target: unknown): boolean {
    return typeof target === "boolean";
}

/**
 * Test if target is a number
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a number type
 */
export function isNumber(target: unknown): boolean {
    return typeof target === "number";
}

/**
 * Test if target is an object
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a object type
 */
export function isObject(target: unknown): boolean {
    return typeof target === "object" && target !== null;
}

/**
 * Test if target is a string
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a string type
 */
export function isString(target: unknown): boolean {
    return typeof target === "string" && target !== null;
}

/**
 * Convert bytes to a human readable string.
 * @param {number} bytes The bytes to convert.
 * @param {number} decimalPlaces The number of places to force.
 * @returns {string} The bytes in human readable string.
 */
export const bytesReadable = (
    bytes: number,
    decimalPlaces: number = undefined,
): string => {
    if (Number.isNaN(bytes)) {
        return "0 Bytes";
    }

    if (Math.abs(bytes) < 1024) {
        return bytes + " Bytes";
    }

    const units = ["KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
    let u = -1;
    const r = 10 ** 1;
    let tempBytes = bytes;

    while (
        Math.round(Math.abs(tempBytes) * r) / r >= 1024 &&
        u < units.length - 1
    ) {
        tempBytes /= 1024;
        ++u;
    }

    if (decimalPlaces === undefined) {
        return tempBytes.toFixed(2).replace(/\.?0+$/, "") + " " + units[u];
    }

    return tempBytes.toFixed(decimalPlaces) + " " + units[u];
};
