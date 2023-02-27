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
 * Generates a random UUID.
 *
 * @returns {string} A random UUID.
 */
export const randomUUID = (): string => {
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
};
