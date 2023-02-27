/**
 * Transforms a string to title case.
 *
 * @param {string} str The string to transform.
 * @returns {string} A string transformed to title case.
 */
export const toTitleCase = (str: string): string => {
    return str.replace(/\w\S*/g, function (txt) {
        return (
            txt.charAt(0).toUpperCase() +
            txt.substring(1).replace(/_/g, " ").toLowerCase()
        );
    });
};

/**
 * Convert a string to a excerpt.
 *
 * @param {string} txt The text to convert.
 * @param {number} maxLen (optional) The maximum length of the excerpt.
 * @param {boolean} strip (optional) Strip HTML tags from the text.
 * @param stripHtml
 * @returns {string} The excerpt.
 */
export const excerpt = (
    txt: string,
    maxLen: number = 150,
    stripHtml: boolean = true
): string => {
    if (stripHtml) {
        txt = stripHtmlTags(replaceHtmlEntites(txt));
    }

    const txtPieces = txt.split(" ");
    const excerptPieces: string[] = [];
    let curLen = 0;

    txtPieces.every((itm) => {
        if (curLen + itm.length >= maxLen) {
            return false;
        }

        excerptPieces.push(itm);
        curLen += itm.length + 1;
        return true;
    });

    return excerptPieces.join(" ") + (curLen < txt.length ? "..." : "");
};

/**
 * String HTML tags from text.
 *
 * @param {string} txt The text to strip tags.
 * @returns {string} The stripped text.
 */
export const stripHtmlTags = (txt: string): string => {
    txt = txt.replace(/<(p|br)([ /]*?>|[ /]+.*?>)/g, " ");
    return txt.replace(/<[a-zA-Z/][^>]+(>|$)/g, "");
};

/**
 * Replace HTML entities with real characters.
 *
 * @param {string} txt The text to transform.
 * @returns {string} Transformed text
 */
export const replaceHtmlEntites = (txt: string): string => {
    const translate_re = /&(nbsp|amp|quot|lt|gt);/g;
    const translate = {
        nbsp: " ",
        amp: "&",
        quot: '"',
        lt: "<",
        gt: ">",
    };

    return txt.replace(translate_re, function (match, entity) {
        return translate[entity];
    });
};
