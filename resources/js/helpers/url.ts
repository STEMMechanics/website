import { RouteLocationNormalizedLoaded, Router } from "vue-router";

export const urlStripAttributes = (url: string): string => {
    const urlObject = new URL(url);
    urlObject.search = "";
    urlObject.hash = "";
    return urlObject.toString();
};

export const urlMatches = (
    fullUrl: string,
    testPath: string | string[],
): boolean | number => {
    // Remove query string and fragment identifier from both URLs
    const urlWithoutParams = fullUrl.split(/[?#]/)[0];

    if (Array.isArray(testPath)) {
        // Iterate over the array of test paths and return the index of the first matching path
        for (let i = 0; i < testPath.length; i++) {
            const pathWithoutParams = testPath[i].split(/[?#]/)[0];
            // Remove trailing slashes from both URLs
            const trimmedUrl = urlWithoutParams.replace(/\/$/, "");
            const trimmedPath = pathWithoutParams.replace(/\/$/, "");
            // Check if both URLs contain a domain and port
            const hasDomainAndPort =
                /^https?:\/\/[^/]+\//.test(trimmedUrl) &&
                /^https?:\/\/[^/]+\//.test(trimmedPath);

            if (hasDomainAndPort) {
                // Do a full test with both URLs
                if (trimmedUrl === trimmedPath) {
                    return i;
                }
            } else {
                // Remove the domain and test the paths
                const urlWithoutDomain = trimmedUrl.replace(
                    /^https?:\/\/[^/]+/,
                    "",
                );
                const pathWithoutDomain = trimmedPath.replace(
                    /^https?:\/\/[^/]+/,
                    "",
                );
                if (urlWithoutDomain === pathWithoutDomain) {
                    return i;
                }
            }
        }
        // If no matching path is found, return false
        return false;
    } else {
        const pathWithoutParams = testPath.split(/[?#]/)[0];
        // Remove trailing slashes from both URLs
        const trimmedUrl = urlWithoutParams.replace(/\/$/, "");
        const trimmedPath = pathWithoutParams.replace(/\/$/, "");
        // Check if both URLs contain a domain and port
        const hasDomainAndPort =
            /^https?:\/\/[^/]+\//.test(trimmedUrl) &&
            /^https?:\/\/[^/]+\//.test(trimmedPath);

        if (hasDomainAndPort) {
            // Do a full test with both URLs
            return trimmedUrl === trimmedPath;
        } else {
            // Remove the domain and test the paths
            const urlWithoutDomain = trimmedUrl.replace(
                /^https?:\/\/[^/]+/,
                "",
            );
            const pathWithoutDomain = trimmedPath.replace(
                /^https?:\/\/[^/]+/,
                "",
            );
            return urlWithoutDomain === pathWithoutDomain;
        }
    }
};

interface Params {
    [key: string]: string;
}

export const updateRouterParams = (router: Router, params: Params): void => {
    const query = { ...router.currentRoute.value.query };

    Object.entries(params).forEach(([key, value]) => {
        if (value === "") {
            if (key in params) {
                delete query[key];
            }
        } else {
            query[key] = value;
        }
    });

    router.push({ query });
};

export const getRouterParam = (
    route: RouteLocationNormalizedLoaded,
    param: string,
    defaultValue: string = "",
): string => {
    if (route.query[param] !== undefined) {
        const val = route.query[param];

        if (Array.isArray(val) == true) {
            if (val.length > 0) {
                return val[0];
            }

            return defaultValue;
        }

        return val.toString();
    }

    return defaultValue;
};

export const extractFileNameFromUrl = (url: string): string => {
    const matches = url.match(/\/([^/]+\.[^/]+)$/);
    if (!matches) {
        return "";
    }
    const fileName = matches[1];
    return fileName;
};
