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

export const mediaGetThumbnail = (
    media: Media | File,
    useVariant: string | null = "",
    callback = null,
): string => {
    let url: string = "";

    if (media) {
        if (media instanceof File) {
            if (callback != null) {
                if (mimeMatches("image/*", media.type) == true) {
                    const reader = new FileReader();

                    reader.onload = function (e) {
                        callback(e.target.result.toString());
                    };

                    reader.readAsDataURL(media);
                    return "";
                }
            }
        } else {
            if (
                useVariant &&
                useVariant != "" &&
                useVariant != null &&
                media.variants &&
                media.variants[useVariant]
            ) {
                url = media.url.replace(media.name, media.variants[useVariant]);
            }

            if (media.thumbnail && media.thumbnail.length > 0) {
                url = media.thumbnail;
            }

            if (media.variants && media.variants["thumb"]) {
                url = media.url.replace(media.name, media.variants["thumb"]);
            }
        }

        if (url === "") {
            url = "/assets/fileicons/unknown.webp";
        }
    }

    if (callback != null) {
        callback(url);
        return "";
    }

    return url;
};
