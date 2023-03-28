export const urlStripAttributes = (url: string): string => {
    const urlObject = new URL(url);
    urlObject.search = "";
    urlObject.hash = "";
    return urlObject.toString();
};

export const urlMatches = (fullUrl: string, testPath: string): boolean => {
    // Remove query string and fragment identifier from both URLs
    const urlWithoutParams = fullUrl.split(/[?#]/)[0];
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
        const urlWithoutDomain = trimmedUrl.replace(/^https?:\/\/[^/]+/, "");
        const pathWithoutDomain = trimmedPath.replace(/^https?:\/\/[^/]+/, "");
        return urlWithoutDomain === pathWithoutDomain;
    }
};
