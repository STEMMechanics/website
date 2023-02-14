const transitionEndEventName = () => {
    var i,
        undefined,
        el = document.createElement("div"),
        transitions = {
            transition: "transitionend",
            OTransition: "otransitionend",
            MozTransition: "transitionend",
            WebkitTransition: "webkitTransitionEnd",
        };

    for (i in transitions) {
        if (transitions.hasOwnProperty(i) && el.style[i] !== undefined) {
            return transitions[i];
        }
    }

    return null;
};

const waitForElementRender = (elem) => {
    return new Promise((resolve) => {
        if (document.contains(elem.value)) {
            return resolve(elem.value);
        }

        const MutationObserver =
            window.MutationObserver ||
            window.WebKitMutationObserver ||
            window.MozMutationObserver;
        const observer = new MutationObserver((mutations) => {
            if (document.contains(elem.value)) {
                resolve(elem.value);
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    });
};

const transitionEnter = (elem, transition) => {
    waitForElementRender(elem).then((e) => {
        window.setTimeout(() => {
            e.classList.replace(
                transition + "-enter-from",
                transition + "-enter-active"
            );
            const transitionName = transitionEndEventName();
            e.addEventListener(
                transitionName,
                () => {
                    e.classList.replace(
                        transition + "-enter-active",
                        transition + "-enter-to"
                    );
                },
                false
            );
        }, 1);
    });
};

const transitionLeave = (elem, transition, callback = null) => {
    elem.value.classList.remove(transition + "-enter-to");
    elem.value.classList.add(transition + "-leave-from");
    window.setTimeout(() => {
        elem.value.classList.replace(
            transition + "-leave-from",
            transition + "-leave-active"
        );
        const transitionName = transitionEndEventName();
        elem.value.addEventListener(
            transitionName,
            () => {
                elem.value.classList.replace(
                    transition + "-leave-active",
                    transition + "-leave-to"
                );
                if (callback) {
                    callback();
                }
            },
            false
        );
    }, 1);
};

export const monthString = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
];

export const fullMonthString = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
];

/**
 *
 * @param target
 */
export function isBool(target) {
    return typeof target === "boolean";
}

/**
 *
 * @param target
 */
export function isNumber(target) {
    return typeof target === "number";
}

/**
 *
 * @param target
 */
export function isObject(target) {
    return typeof target === "object" && target !== null;
}

/**
 *
 * @param target
 */
export function isString(target) {
    return typeof target === "string" && target !== null;
}

/**
 *
 * @param target
 * @param def
 */
export function parseErrorType(
    target,
    def = "An unknown error occurred. Please try again later."
) {
    if (target.response?.message) {
        return target.response.message;
    } else if (target instanceof Error) {
        target.message;
    } else if (isString(err)) {
        return target;
    }

    return def;
}

export const buildUrlQuery = (url, query) => {
    let s = "";

    if (Object.keys(query).length > 0) {
        s = "?";
    }

    s += Object.keys(query)
        .map((key) => key + "=" + query[key])
        .join("&");

    return url + s;
};

export const toParamString = (obj, q = true) => {
    let s = "";

    if (q && Object.keys(obj).length > 0) {
        s = "?";
    }

    s += Object.keys(obj)
        .map((key) => key + "=" + obj[key])
        .join("&");
    return s;
};

export const getLocale = () => {
    return (
        navigator.userLanguage ||
        (navigator.languages &&
            navigator.languages.length &&
            navigator.languages[0]) ||
        navigator.language ||
        navigator.browserLanguage ||
        navigator.systemLanguage ||
        "en"
    );
};

export const debounce = (fn, delay) => {
    var timeoutID = null;
    return function () {
        clearTimeout(timeoutID);
        var args = arguments;
        var that = this;
        timeoutID = setTimeout(function () {
            fn.apply(that, args);
        }, delay);
    };
};

export const bytesReadable = (bytes) => {
    if (isNaN(bytes)) {
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

export const arrayIncludesMatchBasic = (arr, str) => {
    let matches = false;

    arr.every((elem) => {
        elem = elem.replace(/[|\\{}()[\]^$+?.]/g, "\\$&");
        let regex = new RegExp("^" + elem.replace("*", ".*?") + "$", "i");
        if (str.match(regex)) {
            matches = true;
        }
        return !matches;
    });

    return matches;
};

export const excerpt = (txt, maxLen = 150, strip = true) => {
    if (strip) {
        txt = stripHtmlTags(replaceHtmlEntites(txt));
    }

    let txtPieces = txt.split(" ");
    let excerptPieces = [];
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

export const stripHtmlTags = (txt) => {
    txt = txt.replace(/<(p|br)([ /]*?>|[ /]+.*?>)/g, " ");
    return txt.replace(/<[a-zA-Z/][^>]+(>|$)/g, "");
};

export const replaceHtmlEntites = (txt) => {
    var translate_re = /&(nbsp|amp|quot|lt|gt);/g;
    var translate = {
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

export const isUUID = (uuid) => {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-5][0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
        uuid
    );
};

export {
    transitionEndEventName,
    waitForElementRender,
    transitionEnter,
    transitionLeave,
};
