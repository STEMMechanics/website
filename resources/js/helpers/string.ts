/**
 * Transforms a string to title case.
 * @param {string} str The string to transform.
 * @returns {string} A string transformed to title case.
 */
export const toTitleCase = (str: string): string => {
    // Replace underscores and hyphens with spaces
    str = str.replace(/[_-]+/g, " ");

    // Capitalize the first letter of each word and make the rest lowercase
    str = str.replace(/\b\w+\b/g, (txt) => {
        return txt.charAt(0).toUpperCase() + txt.slice(1).toLowerCase();
    });

    // Replace "cdn" with "CDN"
    str = str.replace(/\bCdn\b/gi, "CDN");

    return str;
};

/**
 * Convert a string to a excerpt.
 * @param {string} txt The text to convert.
 * @param {number} maxLen (optional) The maximum length of the excerpt.
 * @param {boolean} strip (optional) Strip HTML tags from the text.
 * @param stripHtml
 * @returns {string} The excerpt.
 */
export function excerpt(
    txt: string,
    maxLen: number = 150,
    stripHtml: boolean = true,
): string {
    if (stripHtml) {
        txt = txt.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ");
    }

    const words = txt.trim().split(/\s+/);
    let curLen = 0;
    const excerptWords: string[] = [];

    for (const word of words) {
        if (curLen + word.length + 1 > maxLen) {
            break;
        }
        curLen += word.length + 1;
        excerptWords.push(word);
    }

    let excerpt = excerptWords.join(" ");
    if (curLen < txt.length) {
        excerpt += "...";
    }

    return excerpt;
}

/**
 * String HTML tags from text.
 * @param {string} txt The text to strip tags.
 * @returns {string} The stripped text.
 */
export const stripHtmlTags = (txt: string): string => {
    return txt.replace(/<(p|br)([ /]*?>|[ /]+.*?>)|<[a-zA-Z/][^>]+(>|$)/g, " ");
};

/**
 * Replace HTML entities with real characters.
 * @param {string} txt The text to transform.
 * @returns {string} Transformed text
 */
export const replaceHtmlEntities = (txt: string): string => {
    const translate_re = /&(nbsp|amp|quot|lt|gt);/g;

    return txt.replace(translate_re, function (match, entity) {
        switch (entity) {
            case "nbsp":
                return " ";
            case "amp":
                return "&";
            case "quot":
                return '"';
            case "lt":
                return "<";
            case "gt":
                return ">";
            default:
                return match;
        }
    });
};

/**
 * Convert a string to a number, ignoring items like dollar signs, etc.
 * @param {string} str The string to convert to a number
 * @returns {number} A number with the minimum amount of decimal places (or 0)
 */
export const stringToNumber = (str: string): number => {
    str = str.replace(/[^\d.-]/g, "");
    const num = parseFloat(str);
    return isNaN(num) ? 0 : Number(num.toFixed(2));
};

/**
 * Convert a number or string to a price (0 or 0.00).
 * @param {number|string} numOrString The number of string to convert to a price.
 * @returns {string} The converted result.
 */
export const toPrice = (numOrString: number | string): string => {
    const num =
        typeof numOrString === "string"
            ? stringToNumber(numOrString)
            : numOrString;
    return num.toFixed(num % 1 === 0 ? 0 : 2);
};

/**
 * Compare 2 strings case insensitive
 * @param {string} string1 The first string for comparison.
 * @param {string} string2 The second string for comparison.
 * @returns {boolean} If the strings match.
 */
export const strCaseCmp = (string1: string, string2: string): boolean => {
    if (string1 !== undefined && string2 !== undefined) {
        return string1.toLowerCase() === string2.toLowerCase();
    }

    return false;
};
