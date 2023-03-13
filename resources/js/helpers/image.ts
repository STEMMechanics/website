import { ImportMetaExtras } from "../../../import-meta";

type ImageLoadCallback = (url: string) => void;

export const imageLoad = (
    url: string,
    callback: ImageLoadCallback,
    postfix = "h=50"
) => {
    callback(`${url}?${postfix}`);
    const tmp = new Image();
    tmp.onload = function () {
        callback(url);
    };
    tmp.src = url;
};

export const imageSize = (size: string, url: string) => {
    const availableSizes = ["thumb", "medium", "large"];
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

export const imageThumb = (url: string) => {
    return imageSize("thumb", url);
};

export const imageMedium = (url: string) => {
    return imageSize("medium", url);
};

export const imageLarge = (url: string) => {
    return imageSize("large", url);
};

export const imageFull = (url: string) => {
    return imageSize("full", url);
};
