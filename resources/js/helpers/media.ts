import { Media } from "./api.types";

export const mediaGetVariantUrl = (
    media: Media,
    variant = "scaled",
): string => {
    if (!media) {
        return "";
    }

    // If the variant is 'original', return the media url
    if (variant === "original") {
        return media.url;
    }

    // If the variant key exists in media.variants, return the corresponding variant URL
    if (media.variants && media.variants[variant]) {
        return media.url.replace(media.name, media.variants[variant]);
    }

    // If the variant key does not exist, return the 'scaled' variant
    return media.variants && media.variants["scaled"]
        ? media.url.replace(media.name, media.variants["scaled"])
        : media.url;
};

export const mimeMatches = (
    mimeExpected: string,
    mimeToCheck: string,
): boolean => {
    const escapedExpectation = mimeExpected.replace(
        /[.*+?^${}()|[\]\\]/g,
        "\\$&",
    );
    const pattern = escapedExpectation.replace(/\\\*/g, ".*");
    const regex = new RegExp(`^${pattern}$`);

    return regex.test(mimeToCheck);
};

export const mediaGetThumbnail = (media: Media): string => {
    if (!media) {
        return "";
    }

    if (media.thumbnail && media.thumbnail.length > 0) {
        return media.thumbnail;
    }

    return mediaGetVariantUrl(media, "thumb");
};
