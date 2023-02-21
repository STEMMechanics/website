/**
 * Test if target is a boolean
 *
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a boolean type
 */
export function isBool(target: unknown): boolean {
    return typeof target === "boolean";
}

/**
 * Test if target is a number
 *
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a number type
 */
export function isNumber(target: unknown): boolean {
    return typeof target === "number";
}

/**
 * Test if target is an object
 *
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a object type
 */
export function isObject(target: unknown): boolean {
    return typeof target === "object" && target !== null;
}

/**
 * Test if target is a string
 *
 * @param {unknown} target The varible to test
 * @returns {boolean} If the varible is a string type
 */
export function isString(target: unknown): boolean {
    return typeof target === "string" && target !== null;
}

/**
 * Test if target is a UUID
 *
 * @param {string} uuid The variable to test
 * @returns {boolean} If the varible is a UUID
 */
export const isUUID = (uuid: string): boolean => {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
        uuid
    );
};

/**
 * Convert bytes to a human readable string.
 *
 * @param {number} bytes The bytes to convert.
 * @returns {string} The bytes in human readable string.
 */
export const bytesReadable = (bytes: number): string => {
    if (Number.isNaN(bytes)) {
        return "0 Bytes";
    }

    if (Math.abs(bytes) < 1024) {
        return bytes + " Bytes";
    }

    const units = ["KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
    let u = -1;
    const r = 10 ** 1;

    do {
        bytes /= 1000;
        ++u;
    } while (
        Math.round(Math.abs(bytes) * r) / r >= 1000 &&
        u < units.length - 1
    );

    return bytes.toFixed(1) + " " + units[u];
};
