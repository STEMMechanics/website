import { ImportMetaExtras } from "../../../import-meta";

type ImageLoadCallback = (url: string) => void;

export const imageLoad = (
    url: string,
    callback: ImageLoadCallback,
    postfix = "size=thumb"
) => {
    callback(`${url}?${postfix}`);
    const tmp = new Image();
    tmp.onload = function () {
        callback(url);
    };
    tmp.src = url;
};

export const imageSize = (size: string, url: string) => {
    const availableSizes = ["thumb", "small", "medium", "large", "xlarge"];
    if (availableSizes.includes(size)) {
        if (
            url.startsWith((import.meta as ImportMetaExtras).env.APP_URL) ===
            true
        ) {
            return `${url}?size=${size}`;
        }
    }

    return url;
};

// Thumb 150 x 150
export const imageThumb = (url: string) => {
    return imageSize("thumb", url);
};

// Small 300 x 300
export const imageSmall = (url: string) => {
    return imageSize("small", url);
};

// Small 640 x 640
export const imageMedium = (url: string) => {
    return imageSize("medium", url);
};

// Large 1024 x 1024
export const imageLarge = (url: string) => {
    return imageSize("large", url);
};

// Large 1536 x 1536
export const imageXLarge = (url: string) => {
    return imageSize("xlarge", url);
};

// Full size
export const imageFull = (url: string) => {
    return imageSize("full", url);
};
