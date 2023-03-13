export const urlStripAttributes = (url: string): string => {
    const urlObject = new URL(url);
    urlObject.search = "";
    urlObject.hash = "";
    return urlObject.toString();
};
